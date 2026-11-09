<?php declare(strict_types=1);

namespace TaxApiMailReaders\Tests\Core\Reichelt;

use PHPUnit\Framework\TestCase;
use TaxApiMailReaders\Core\Reichelt\ReicheltMailParser;
use Taxmod\Core\Import\Mail\MimeParser;

/**
 * Each expectation is what the Python importer (legacy/ in Taxmod Mail) read from the same synthetic mail.
 */
final class ReicheltMailParserTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function read(string $fixture): array
    {
        $parser = new ReicheltMailParser('0.0.1');
        $mail = (new MimeParser())->parse(1, (string) file_get_contents(__DIR__ . '/fixtures/' . $fixture . '.eml'));
        self::assertTrue($parser->recognises($mail), 'not recognised: ' . $fixture);

        return $parser->parse($mail)->toArray();
    }

    public function testReicheltConfirmation(): void
    {
        $order = self::read('r1');

        self::assertSame(['Reichelt', 'I-123456', 'ordered', '5.95', '19.05'], [$order['platform'], $order['order_number'], $order['kind'], $order['shipping_cost'], $order['total']]);
        self::assertSame([
            ['title' => 'LM317 Spannungsregler 1,5A', 'quantity' => 10.0, 'unit_price' => '0.52', 'article_number' => 'LM 317'],
            ['title' => 'Lochrasterplatine 75x100', 'quantity' => 2.0, 'unit_price' => '3.95', 'article_number' => 'H25PR075'],
        ], $order['lines']);
    }

    public function testReicheltShippingInfo(): void
    {
        $order = self::read('r2');

        self::assertSame(['shipped', 'I-123456', ['00340434610895']], [$order['kind'], $order['order_number'], $order['tracking']]);
    }

    public function testItNamesWhatItReads(): void
    {
        self::assertNotSame([], (new ReicheltMailParser('1.2.3'))->kinds());
        self::assertSame('1.2.3', (new ReicheltMailParser('1.2.3'))->version());
    }
}
