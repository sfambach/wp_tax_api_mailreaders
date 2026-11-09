<?php declare(strict_types=1);

namespace TaxApiMailReaders\WordPress;

use TaxApiMailReaders\Core\AliExpress\AliExpressMailParser;
use TaxApiMailReaders\Core\Amazon\AmazonMailParser;
use TaxApiMailReaders\Core\Conrad\ConradMailParser;
use TaxApiMailReaders\Core\Hermes\HermesNoticeMailParser;
use TaxApiMailReaders\Core\Paypal\PaypalMailParser;
use TaxApiMailReaders\Core\Reichelt\ReicheltMailParser;
use Taxmod\Core\Import\Mail\OrderMailParser;
use Taxmod\WordPress\Import\Documents;

/**
 * The boundary: registers every mail reader of this module with Taxmod Mail (filter `taxmail_parsers`) and gives each
 * its own tab on the modeller's Configuration page, as the single modules did before they were joined (D-1006, D-1007).
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class Plugin
{
    public const VERSION = '0.1.0';

    public static function boot(): void
    {
        add_action('plugins_loaded', static function (): void {
            load_plugin_textdomain('taxapimailreaders', false, dirname(plugin_basename(dirname(__DIR__, 2) . '/wp-tax-api-mailreaders.php')) . '/languages');

            // The contract lives in the modeller; without it there is nothing to register with.
            if (! interface_exists(OrderMailParser::class)) {
                add_action('admin_notices', static fn () => print '<div class="notice notice-warning"><p><strong>Taxmod Mail Readers:</strong> '
                    . esc_html__('needs the Taxonomy Modeller 0.1.143 or later and Taxmod Mail 0.6.0 or later.', 'taxapimailreaders') . '</p></div>');

                return;
            }

            $readers = self::readers();
            add_filter('taxmail_parsers', static fn (array $parsers): array => [...$parsers, ...array_column($readers, 'reader')]);
            add_filter('taxmod_config_tabs', static function (array $tabs) use ($readers): array {
                foreach ($readers as $tab => $each) {
                    $tabs += [$tab => [
                        'title'   => $each['title'],
                        'version' => self::VERSION,
                        'reader'  => $each['reader'],
                        'guide'   => $each['guide'],
                        'render'  => static fn (): string => '',
                        'save'    => static function (array $post): void {
                        },
                    ]];
                }

                return $tabs;
            }, 30);
        });
    }

    /**
     * One entry per shop or carrier; the tab key stays the key of the module it came from, so stored tab links still work.
     *
     * @return array<string, array{title: string, reader: OrderMailParser, guide: \Closure(): string}>
     */
    private static function readers(): array
    {
        return [
            'aliexpress' => ['title' => 'AliExpress', 'reader' => new AliExpressMailParser(self::VERSION), 'guide' => self::aliexpressGuide(...)],
            'amazon'     => ['title' => 'Amazon', 'reader' => new AmazonMailParser(self::VERSION), 'guide' => self::amazonGuide(...)],
            // Invoice PDFs go to the modeller's encrypted document store (D-1023); without it only the facts are kept.
            'conrad'     => ['title' => 'Conrad', 'reader' => new ConradMailParser(self::VERSION, class_exists(Documents::class) ? Documents::put(...) : null), 'guide' => self::conradGuide(...)],
            'reichelt'   => ['title' => 'Reichelt', 'reader' => new ReicheltMailParser(self::VERSION), 'guide' => self::reicheltGuide(...)],
            'hermes'     => ['title' => 'Hermes', 'reader' => new HermesNoticeMailParser(self::VERSION), 'guide' => self::hermesGuide(...)],
            'paypal'     => ['title' => 'PayPal', 'reader' => new PaypalMailParser(self::VERSION, self::shopsBesidesPaypal(...)), 'guide' => self::paypalGuide(...)],
        ];
    }

    /**
     * Shops other readers handle; a payment to one of them is that reader's order, not a second one from PayPal.
     *
     * @return list<string>
     */
    public static function shopsBesidesPaypal(): array
    {
        $names = [];

        foreach ((array) apply_filters('taxmail_parsers', []) as $parser) {
            if ($parser instanceof OrderMailParser && $parser->key() !== 'paypal') {
                $names[] = $parser->shopName();
            }
        }

        return $names;
    }

    /** The boxes «How to connect» beside each tab (D-963). */
    public static function aliexpressGuide(): string
    {
        return '<ol><li>' . esc_html__('Let AliExpress send its order mails to the mailbox of Taxmod Mail (or forward them there).', 'taxapimailreaders') . '</li><li>'
            . esc_html__('Keep this module active: Taxmod Mail hands every AliExpress mail to it, and the proposals appear in the Inbox.', 'taxapimailreaders') . '</li></ol><p>'
            . esc_html__('A shipping mail carries no order date.', 'taxapimailreaders') . '</p>';
    }

    public static function amazonGuide(): string
    {
        return '<ol><li>' . esc_html__('Let Amazon send its order mails to the mailbox of Taxmod Mail (or forward them there).', 'taxapimailreaders') . '</li><li>'
            . esc_html__('Keep this module active: Taxmod Mail hands every Amazon mail to it, and the proposals appear in the Inbox.', 'taxapimailreaders') . '</li></ol><p>'
            . esc_html__('The mails name no tracking number and no shipping cost. The order history from before the mails is uploaded in the Mail module for now.', 'taxapimailreaders') . '</p>';
    }

    public static function conradGuide(): string
    {
        return '<ol><li>' . esc_html__('Let Conrad send its order mails to the mailbox of Taxmod Mail (or forward them there).', 'taxapimailreaders') . '</li><li>'
            . esc_html__('Keep this module active: Taxmod Mail hands every Conrad mail to it, and the proposals appear in the Inbox.', 'taxapimailreaders') . '</li></ol><p>'
            . esc_html__('An invoice becomes a record «Rechnung» linked to its order: number, date, amount, paid or open, and the PDF, which is kept encrypted.', 'taxapimailreaders') . '</p>';
    }

    public static function reicheltGuide(): string
    {
        return '<ol><li>' . esc_html__('Let Reichelt send its order mails to the mailbox of Taxmod Mail (or forward them there).', 'taxapimailreaders') . '</li><li>'
            . esc_html__('Keep this module active: Taxmod Mail hands every Reichelt mail to it, and the proposals appear in the Inbox.', 'taxapimailreaders') . '</li></ol><p>'
            . esc_html__('Reichelt has no API; mails are the only way.', 'taxapimailreaders') . '</p>';
    }

    public static function hermesGuide(): string
    {
        return '<ol><li>' . esc_html__('Let Hermes send its parcel notices to the mailbox of Taxmod Mail (or forward them there).', 'taxapimailreaders') . '</li><li>'
            . esc_html__('Keep this module active: each notice goes to the order with the same tracking number; it never creates an order.', 'taxapimailreaders') . '</li></ol><p>'
            . esc_html__('Hermes offers recipients no public API, so the mails are the only source.', 'taxapimailreaders') . '</p>';
    }

    public static function paypalGuide(): string
    {
        return '<ol><li>' . esc_html__('Let PayPal send its payment receipts to the mailbox of Taxmod Mail (or forward them there).', 'taxapimailreaders') . '</li><li>'
            . esc_html__('Keep this module active: Taxmod Mail hands every PayPal mail to it, and the proposals appear in the Inbox.', 'taxapimailreaders') . '</li></ol><p>'
            . esc_html__('A payment to a shop with a module of its own (eBay, AliExpress …) is left to that module and discarded here. Every other payment becomes an order of the platform «PayPal», numbered by its transaction code.', 'taxapimailreaders') . '</p>';
    }
}
