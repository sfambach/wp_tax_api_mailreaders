<?php declare(strict_types=1);

namespace TaxApiMailReaders\Core\Lcsc;

use Smalot\PdfParser\Parser;

/**
 * The items of an LCSC packing list PDF: LCSC part number, manufacturer part number, description and quantities.
 * LCSC's shipping mail names only order and tracking number; what was bought stands in this attachment alone, without prices.
 *
 * ```mermaid
 * flowchart LR
 *   H["«7 C137115» item line"] --> D["Mfr. Part # + description lines"] --> Q["«100 100 3 TW» quantities"]
 *   Q -->|next item| H
 * ```
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class LcscPackingList
{
    /** One item starts with its position and LCSC part number; the description may follow on the same line. */
    private const ITEM = '/^(\d{1,3}) (C\d{3,10})\b\s*(.*)$/u';
    /** Ordered, shipped, net weight in grams, country of origin. */
    private const QUANTITIES = '/^(\d+) (\d+) [\d.]+ [A-Z]{2}$/';
    private const MANUFACTURER = '/^Mfr\. Part #:\s*(.*)$/u';

    /** @param list<array{lcsc: string, manufacturerPart: ?string, description: string, ordered: int, shipped: int}> $items */
    private function __construct(public readonly array $items)
    {
    }

    /** Null when the bytes are no readable PDF. */
    public static function read(string $pdf): ?self
    {
        try {
            $text = (new Parser())->parseContent($pdf)->getText();
        } catch (\Throwable) {
            // A broken attachment leaves the shipping mail without items; its order and tracking number still count.
            return null;
        }

        return self::fromText($text);
    }

    public static function fromText(string $text): self
    {
        $items = [];
        $block = [];

        // The garbled part numbers are no valid UTF-8, and a single bad byte makes every /u pattern fail.
        foreach (preg_split('/\R/u', mb_scrub($text, 'UTF-8')) ?: [] as $raw) {
            $line = trim($raw);

            if ($line === '') {
                continue;
            }

            if (preg_match(self::QUANTITIES, $line, $quantities) === 1) {
                $item = self::item($block, (int) $quantities[1], (int) $quantities[2]);
                $block = [];

                if ($item !== null) {
                    $items[] = $item;
                }

                continue;
            }

            $block[] = $line;
        }

        return new self($items);
    }

    /**
     * The lines between two quantity rows: the item line, and the manufacturer part before or after it (the layout varies).
     *
     * @param list<string> $block
     * @return array{lcsc: string, manufacturerPart: ?string, description: string, ordered: int, shipped: int}|null
     */
    private static function item(array $block, int $ordered, int $shipped): ?array
    {
        $lcsc = null;
        $manufacturerPart = null;
        $description = [];

        for ($k = 0; $k < count($block); $k++) {
            if ($lcsc === null && preg_match(self::ITEM, $block[$k], $head) === 1) {
                $lcsc = $head[2];
                $description = $head[3] !== '' ? [$head[3]] : [];
            } elseif (preg_match(self::MANUFACTURER, $block[$k], $part) === 1) {
                $manufacturerPart = $part[1];

                // A long part number wraps: «MMBT3904(RANGE:100-» / «300)».
                if (isset($block[$k + 1]) && (str_ends_with($manufacturerPart, '-') || substr_count($manufacturerPart, '(') > substr_count($manufacturerPart, ')'))) {
                    $manufacturerPart .= $block[++$k];
                }
            } elseif ($lcsc !== null) {
                $description[] = $block[$k];
            }
        }

        if ($lcsc === null) {
            return null;
        }

        // Some part numbers are set in a font without a text map and come out as control bytes; such a number says nothing.
        if ($manufacturerPart !== null && preg_match('/^[\x20-\x7E]+$/', $manufacturerPart) !== 1) {
            $manufacturerPart = null;
        }

        // A word broken at its hyphen («SOT-» / «23») joins without a space; a free-standing « - » keeps it.
        $joined = '';

        foreach ($description as $part) {
            $joined .= ($joined === '' || preg_match('/\S-$/u', $joined) === 1 ? '' : ' ') . $part;
        }

        $text = preg_replace('/\s+(?:ROHS|RoHS)$/u', '', $joined) ?? '';

        return ['lcsc' => $lcsc, 'manufacturerPart' => $manufacturerPart, 'description' => preg_replace('/\s+/u', ' ', $text) ?? $text,
            'ordered' => $ordered, 'shipped' => $shipped];
    }
}
