<?php declare(strict_types=1);

namespace TaxApiMailReaders\Core\Lcsc;

use Taxmod\Core\Import\Mail\LineCollector;
use Taxmod\Core\Import\Mail\MailMessage;
use Taxmod\Core\Import\Mail\ShopMailParser;
use Taxmod\Core\Import\OrderProposal;
use Taxmod\Core\Import\ProposalKind;

/**
 * LCSC: order confirmation (order number only) and shipping notification (order, tracking number, and the items from the
 * attached packing list). The mails carry no prices; the order comes in from the shipping mail, since it alone names the items.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class LcscMailParser extends ShopMailParser
{
    private const MERCHANT = 'LCSC';
    /** «WM2504190174»: WM, the order day as yymmdd, a running number. */
    private const ORDER = '/\b(WM(\d{2})(\d{2})(\d{2})\d{4})\b/';

    public function __construct(string $version)
    {
        parent::__construct('lcsc', 'LCSC', [ProposalKind::Ordered, ProposalKind::Shipped], ['lcsc.com'], $version);
    }

    public function parse(MailMessage $mail): OrderProposal
    {
        $text = implode("\n", $mail->lines());

        if (preg_match_all(self::ORDER, $text, $orders, PREG_SET_ORDER) === 0) {
            return new OrderProposal($this->shopName(), '', ProposalKind::NoReceipt);
        }

        $number = $orders[0][1];
        $ordered = sprintf('20%s-%s-%s', $orders[0][2], $orders[0][3], $orders[0][4]);

        if (! str_contains($mail->subject, 'Shipped')) {
            return new OrderProposal($this->shopName(), $number, ProposalKind::Ordered, $ordered, counterparty: self::MERCHANT);
        }

        $tracking = preg_match('/Tracking number:\s*([A-Z0-9]+)/', $text, $found) === 1 ? [$found[1]] : [];
        $shipper = preg_match('/Shipping method:\s*([^\n]+?)\.?\s*$/m', $text, $method) === 1 ? $method[1] : null;
        // A batch of several orders has one packing list for all; which item belongs to which order it does not say.
        $single = count(array_unique(array_column($orders, 1))) === 1;
        $list = $single && ($pdf = $mail->pdfs()[0] ?? null) !== null ? LcscPackingList::read($pdf->content) : null;
        $items = new LineCollector();

        foreach ($list->items ?? [] as $item) {
            $title = $item['manufacturerPart'] !== null ? $item['manufacturerPart'] . ' – ' . $item['description'] : $item['description'];
            // LCSC refunds what it could not ship, so the purchase is what was shipped.
            $items->add($title, (float) $item['shipped'], null, $item['lcsc']);
        }

        return new OrderProposal($this->shopName(), $number, ProposalKind::Shipped, $ordered, lines: $items->lines(), counterparty: self::MERCHANT,
            shippedDate: $mail->date?->format('Y-m-d'), trackingNumbers: $tracking, shipper: $shipper);
    }
}
