<?php

/**
 * NotdefMetricsTest.php
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Test;

use Com\Tecnick\File\Byte;
use Com\Tecnick\Pdf\Font\Import\TrueType;
use Com\Tecnick\Pdf\Font\Load;

/**
 * A codepoint a cmap maps to .notdef declares no metrics.
 *
 * A TrueType cmap may point a codepoint at glyph 0 explicitly. No advance and no bounding
 * box are recorded for it, so Stack::isCharDefined() reports it as missing and
 * Stack::replaceMissingChars() can substitute a fallback.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class NotdefMetricsTest extends TestUtil
{
    /**
     * Advances of the three glyphs of the synthetic hmtx below, in font units.
     *
     * @var array<int, int>
     */
    private const ADVANCES = [0 => 500, 1 => 700, 2 => 900];

    /**
     * Builds a font whose hmtx holds the advances above and whose glyf holds one header per
     * glyph, then runs getWidths() over the given cmap result.
     *
     * @param array<int, int> $ctgdata Glyph index for each codepoint, as a cmap yields it.
     *
     * @return array<string, mixed> The resulting font metrics.
     */
    private function runGetWidths(array $ctgdata): array
    {
        $hmtx = '';
        foreach (self::ADVANCES as $advance) {
            $hmtx .= \pack('nn', $advance, 0); // advanceWidth, lsb
        }

        // one glyph header per glyph: numberOfContours and the four corners of the box,
        // each glyph carrying its own index as the corner value so they are told apart
        $glyf = '';
        $indexToLoc = [];
        foreach (\array_keys(self::ADVANCES) as $gid) {
            $indexToLoc[$gid] = \strlen($glyf);
            $glyf .= \pack('nnnnn', 1, $gid + 1, $gid + 1, $gid + 1, $gid + 1);
        }

        $indexToLoc[\count(self::ADVANCES)] = \strlen($glyf);

        $font = $hmtx . $glyf;
        $fdt = \array_replace(Load::DEFAULT_DATA, [
            'ctgdata' => $ctgdata,
            'indexToLoc' => $indexToLoc,
            'numGlyphs' => \count(self::ADVANCES),
            'numHMetrics' => \count(self::ADVANCES),
            'short_offset' => false,
            'table' => [
                'hmtx' => ['offset' => 0, 'length' => \strlen($hmtx), 'checkSum' => 0, 'data' => ''],
                'glyf' => ['offset' => \strlen($hmtx), 'length' => \strlen($glyf), 'checkSum' => 0, 'data' => ''],
                'hhea' => ['offset' => 0, 'length' => 36, 'checkSum' => 0, 'data' => ''],
            ],
            'urk' => 1.0,
        ]);

        $instance = (new \ReflectionClass(TrueType::class))->newInstanceWithoutConstructor();
        try {
            $byte = new Byte($font);
        } catch (\RangeException $exception) {
            $this->fail($exception->getMessage());
        }

        foreach ([
            'font' => $font,
            'fdt' => $fdt,
            'fbyte' => $byte,
            'offset' => 0,
            'withCbbox' => true,
        ] as $name => $value) {
            (new \ReflectionProperty(TrueType::class, $name))->setValue($instance, $value);
        }

        (new \ReflectionMethod(TrueType::class, 'getWidths'))->invoke($instance);

        /** @var array<string, mixed> $data */
        $data = (new \ReflectionProperty(TrueType::class, 'fdt'))->getValue($instance);

        return $data;
    }

    /**
     * @return array<int, mixed>
     */
    private function getMap(mixed $value): array
    {
        if (!\is_array($value)) {
            $this->fail('Expected a map indexed by codepoint.');
        }

        /** @var array<int, mixed> $value */
        return $value;
    }

    public function testACodepointMappedToNotdefDeclaresNoWidthAndNoBox(): void
    {
        $data = $this->runGetWidths([
            0x41 => 1,
            0x42 => 0, // the cmap points this codepoint at .notdef
            0x43 => 2,
        ]);

        $widths = $this->getMap($data['cw'] ?? null);
        $this->assertSame(self::ADVANCES[1] ?? null, $widths[0x41] ?? null, 'the glyph the font carries');
        $this->assertSame(self::ADVANCES[2] ?? null, $widths[0x43] ?? null);
        $this->assertArrayNotHasKey(0x42, $widths, 'the codepoint without a glyph');

        $boxes = $this->getMap($data['cbbox'] ?? null);
        $this->assertArrayHasKey(0x41, $boxes);
        $this->assertArrayNotHasKey(0x42, $boxes);
    }

    /**
     * The map from codepoint to glyph index keeps the entry: it is a faithful copy of the
     * cmap, and the CIDToGIDMap artifact is built from it.
     */
    public function testTheGlyphIndexMapKeepsTheNotdefEntry(): void
    {
        $data = $this->runGetWidths([0x41 => 1, 0x42 => 0]);

        $ctgdata = $this->getMap($data['ctgdata'] ?? null);
        $this->assertSame(0, $ctgdata[0x42] ?? null);
    }

    /**
     * The missing width describes the .notdef advance, which is what a document measuring
     * the codepoint falls back to.
     */
    public function testTheMissingWidthStillDescribesTheNotdefAdvance(): void
    {
        $data = $this->runGetWidths([0x41 => 1, 0x42 => 0]);

        $this->assertSame(self::ADVANCES[0] ?? null, $data['MissingWidth'] ?? null);
    }
}
