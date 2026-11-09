<?php declare(strict_types=1);

namespace TaxApiMailReaders\Core\Hermes;

use Taxmod\Core\Import\Mail\MailMessage;
use Taxmod\Core\Import\Mail\OrderMailParser;
use Taxmod\Core\Import\OrderProposal;
use Taxmod\Core\Import\ProposalKind;

/**
 * Hermes's parcel notices («Deine Sendung ist unterwegs», «Erfolgreiche Haustürzustellung»): no shop order, only the
 * tracking number. The modeller gives them to the order that carries that number, whatever shop it is from (D-1020).
 * Hermes offers recipients no public API, so the mail is the only source.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class HermesNoticeMailParser implements OrderMailParser
{
    private const DOMAINS = ['myhermes.de', 'hermesworld.com', 'hermes-europe.de'];

    public function __construct(private readonly string $version)
    {
    }

    public function key(): string
    {
        return 'hermes';
    }

    public function shopName(): string
    {
        return 'Hermes';
    }

    public function recognises(MailMessage $mail): bool
    {
        $domain = $mail->senderDomain();

        return array_filter(self::DOMAINS, static fn (string $hermes): bool => $domain === $hermes || str_ends_with($domain, '.' . $hermes)) !== [];
    }

    public function parse(MailMessage $mail): OrderProposal
    {
        $number = $this->trackingNumber($mail);
        $kind = $this->kind($mail);

        if ($kind === ProposalKind::NoReceipt || $number === null) {
            return new OrderProposal($this->shopName(), '', ProposalKind::NoReceipt);
        }

        if ($kind !== ProposalKind::Shipped) {
            return new OrderProposal($this->shopName(), 'sendung:' . $number, $kind, trackingNumbers: [$number], numberMissing: true);
        }

        [$day, $hours] = $this->announcedDelivery($mail);
        // ⚠️ *A modeller before 0.1.143 knows no announcement (D-1131) and would refuse the named arguments; it gets the
        // notice without them.*
        $announced = property_exists(OrderProposal::class, 'shipper')
            ? ['expectedDelivery' => $day, 'deliveryWindow' => $hours, 'shipper' => $this->shipper($mail)]
            : [];

        return new OrderProposal(...['platform' => $this->shopName(), 'orderNumber' => 'sendung:' . $number, 'kind' => $kind,
            'trackingNumbers' => [$number], 'numberMissing' => true, ...$announced]);
    }

    public function kinds(): array
    {
        return [ProposalKind::Shipped, ProposalKind::Delivered];
    }

    public function version(): string
    {
        return $this->version;
    }

    /**
     * Subject and first sentence together, because Hermes words the subject freely («Erfolgreiche Haustürzustellung»).
     * ⚠️ *«wird voraussichtlich am Dienstag zugestellt» is a forecast, not a delivery: tested against seven of his real
     * mails (2021–2026), four «auf dem Weg» read as delivered before. A delay («Wichtige Information zur Zustellung»)
     * and a parcel waiting in a PaketShop are still on the way.*
     */
    private function kind(MailMessage $mail): ProposalKind
    {
        $said = mb_strtolower($mail->subject . ' ' . ($mail->lines()[0] ?? ''));
        $forecast = preg_match('/voraussichtlich|erst am|kommt leider/u', $said) === 1;

        return match (true) {
            ! $forecast && ! str_contains($said, 'paketshop')
                && preg_match('/wurde[^.]*zugestellt|erfolgreich[^.]*zugestellt|haustürzustellung|beim nachbarn|abgestellt|zustellung erfolgt/u', $said) === 1 => ProposalKind::Delivered,
            preg_match('/unterwegs|auf dem weg|kommt heute|in zustellung|zustellung|angekündigt|paketshop|übergeben|voraussichtlich/u', $said) === 1 => ProposalKind::Shipped,
            default => ProposalKind::NoReceipt,
        };
    }

    /**
     * The day under «Voraussichtliche Zustellung» and the hours «zwischen 09:45 und 11:45 Uhr» below it (D-1123). The old
     * layout (2021) splits the day over several lines («Mi.», «22.12.», «Mittwoch, 22.12.2021»), so the first full date
     * after the heading counts.
     *
     * @return array{0: ?string, 1: ?string} Y-m-d and «HH:MM–HH:MM»
     */
    private function announcedDelivery(MailMessage $mail): array
    {
        $after = false;
        $day = null;

        foreach ($mail->lines() as $line) {
            if (! $after) {
                $after = str_contains($line, 'Voraussichtliche Zustellung');

                continue;
            }

            if ($day === null && preg_match('/\b(\d{2})\.(\d{2})\.(\d{4})\b/', $line, $date) === 1) {
                $day = $date[3] . '-' . $date[2] . '-' . $date[1];

                continue;
            }

            if ($day !== null) {
                return [$day, preg_match('/zwischen (\d{1,2}:\d{2}) und (\d{1,2}:\d{2}) Uhr/u', $line, $hours) === 1 ? $hours[1] . '–' . $hours[2] : null];
            }
        }

        return [$day, null];
    }

    /**
     * «Deine Hermes Sendung von AliExpress ist auf dem Weg», «Deine Sendung von Cainiao ist in Zustellung …», or «deine
     * Sendung von» with the name on the next line. The old layout (2021) names nobody.
     */
    private function shipper(MailMessage $mail): ?string
    {
        $lines = $mail->lines();

        foreach ([$mail->subject, ...$lines] as $line) {
            if (preg_match('/Sendung von (.+?) ist (?:auf dem Weg|in Zustellung|unterwegs)/u', $line, $found) === 1) {
                return trim($found[1]);
            }
        }

        foreach ($lines as $k => $line) {
            if (preg_match('/^deine Sendung von$/iu', trim($line)) === 1 && trim($lines[$k + 1] ?? '') !== '') {
                return trim($lines[$k + 1]);
            }
        }

        return null;
    }

    /** «Sendungsnummer H1033…» on one line, or the number on the next; the tracking link carries it after «#». */
    private function trackingNumber(MailMessage $mail): ?string
    {
        $lines = $mail->lines();

        foreach ($lines as $k => $line) {
            if (! str_contains($line, 'Sendungsnummer')) {
                continue;
            }

            if (preg_match('/Sendungsnummer:?\s+([0-9A-Z]{10,40})(?![0-9A-Z])/', $line, $found) === 1
                || preg_match('/^([0-9A-Z]{10,40})$/', $lines[$k + 1] ?? '', $found) === 1) {
                return $found[1];
            }
        }

        return preg_match('/sendungsverfolgung\/[^"\'\s]*#([0-9A-Z]{10,40})\b/', $mail->html, $found) === 1 ? $found[1] : null;
    }
}
