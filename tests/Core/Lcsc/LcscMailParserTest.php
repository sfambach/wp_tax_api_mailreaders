<?php declare(strict_types=1);

namespace TaxApiMailReaders\Tests\Core\Lcsc;

use PHPUnit\Framework\TestCase;
use TaxApiMailReaders\Core\Lcsc\LcscMailParser;
use TaxApiMailReaders\Core\Lcsc\LcscPackingList;
use Taxmod\Core\Import\Mail\MimeParser;

/**
 * The sample mails are synthetic; their layout follows LCSC's mails of 2025 and 2026 (order confirmation, shipping
 * notification with the packing list as PDF).
 */
final class LcscMailParserTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function read(string $fixture): array
    {
        $parser = new LcscMailParser('0.0.1');
        $mail = (new MimeParser())->parse(1, (string) file_get_contents(__DIR__ . '/fixtures/' . $fixture . '.eml'));
        self::assertTrue($parser->recognises($mail), 'not recognised: ' . $fixture);

        return $parser->parse($mail)->toArray();
    }

    public function testConfirmationNamesTheOrderOnly(): void
    {
        $order = self::read('l1');

        self::assertSame(['LCSC', 'WM2601010001', 'ordered', '2026-01-01', []], [$order['platform'], $order['order_number'], $order['kind'], $order['order_date'], $order['lines']]);
    }

    public function testShippingMailBringsTrackingAndTheItemsOfThePackingList(): void
    {
        $order = self::read('l2');

        self::assertSame(['WM2601010001', 'shipped', '2026-01-01', '2026-01-02', ['4PX0000000000001CN'], 'International Registered Mail', 'LCSC'],
            [$order['order_number'], $order['kind'], $order['order_date'], $order['shipped_date'], $order['tracking'], $order['shipper'], $order['counterparty']]);
        self::assertSame([
            ['title' => 'MMBT3904(RANGE:100-300) – Bipolar (BJT) Transistor NPN 40V Mount SOT-23', 'quantity' => 50.0, 'unit_price' => null, 'article_number' => 'C20526'],
            ['title' => 'RT1206BRD075KL – 250mW Thin Film Resistor 200V Resistor - Surface Mount', 'quantity' => 10.0, 'unit_price' => null, 'article_number' => 'C870818'],
            ['title' => 'Battery Holder Coin CR2032 Cell SMD', 'quantity' => 8.0, 'unit_price' => null, 'article_number' => 'C70377'],
        ], array_map(static fn (array $line): array => array_intersect_key($line, array_flip(['title', 'quantity', 'unit_price', 'article_number'])), $order['lines']));
    }

    public function testABatchOfSeveralOrdersLeavesTheItemsOut(): void
    {
        $order = self::read('l3');

        self::assertSame(['WM2601010001', ['4PX0000000000001CN'], []], [$order['order_number'], $order['tracking'], $order['lines']]);
    }

    public function testAGarbledPartNumberIsDropped(): void
    {
        $list = LcscPackingList::fromText("13 C496603\nMfr. Part #: S\x00\xF9\xEE\xFD\n30V 25A N-channel TO-252 MOSFETs ROHS\n20 20 8.74 TW");

        self::assertSame([['lcsc' => 'C496603', 'manufacturerPart' => null, 'description' => '30V 25A N-channel TO-252 MOSFETs', 'ordered' => 20, 'shipped' => 20]], $list->items);
    }

    public function testOtherSendersAreNotLcsc(): void
    {
        $mail = (new MimeParser())->parse(1, "From: shop@example.com\r\nSubject: Order\r\n\r\nWM2601010001");

        self::assertFalse((new LcscMailParser('1.2.3'))->recognises($mail));
        self::assertSame('1.2.3', (new LcscMailParser('1.2.3'))->version());
    }
}
