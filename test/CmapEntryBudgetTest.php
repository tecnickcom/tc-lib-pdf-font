<?php

/**
 * CmapEntryBudgetTest.php
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
 * Budget of character codes a cmap subtable may map.
 *
 * The budget bounds the metrics extracted from a font program by the number of glyphs it
 * carries. The terminating segment of a format 4 subtable maps nothing and is not charged.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class CmapEntryBudgetTest extends TestUtil
{
    /**
     * Build a format 4 subtable holding one mapping segment and the terminating one.
     *
     * @param int $endCode End character code of the mapping segment, which starts at zero.
     */
    private function subtable(int $endCode): string
    {
        return (
            "\x00\x04" // format
            . "\x00\x20" // length: 16 + (8 * 2 segments)
            . "\x00\x00" // language
            . "\x00\x04" // segCountX2
            . "\x00\x04" // searchRange
            . "\x00\x01" // entrySelector
            . "\x00\x00" // rangeShift
            . \pack('n', $endCode) // endCode[0]
            . "\xFF\xFF" // endCode[1]: the terminating segment
            . "\x00\x00" // reservedPad
            . "\x00\x00" // startCode[0]
            . "\xFF\xFF" // startCode[1]
            . "\x00\x01" // idDelta[0]
            . "\x00\x01" // idDelta[1]
            . "\x00\x00" // idRangeOffset[0]
            . "\x00\x00" // idRangeOffset[1]
        );
    }

    /**
     * Build a TrueType importer over a synthetic font holding a single cmap subtable.
     *
     * @param int $totNumGlyphs Number of glyphs the loca table accounts for.
     */
    private function build(string $subtable, int $totNumGlyphs): TrueType
    {
        $class = new \ReflectionClass(TrueType::class);
        $instance = $class->newInstanceWithoutConstructor();

        try {
            $byte = new Byte($subtable);
        } catch (\RangeException $exception) {
            $this->fail($exception->getMessage());
        }

        $fdt = \array_replace(Load::DEFAULT_DATA, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => [
                'cmap' => ['checkSum' => 0, 'data' => '', 'offset' => 0, 'length' => \strlen($subtable)],
            ],
            'tot_num_glyphs' => $totNumGlyphs,
            'type' => 'TrueTypeUnicode',
        ]);

        (new \ReflectionProperty(TrueType::class, 'font'))->setValue($instance, $subtable);
        (new \ReflectionProperty(TrueType::class, 'fdt'))->setValue($instance, $fdt);
        (new \ReflectionProperty(TrueType::class, 'fbyte'))->setValue($instance, $byte);
        (new \ReflectionProperty(TrueType::class, 'offset'))->setValue($instance, 0);

        return $instance;
    }

    /**
     * @return array<int, int>
     */
    private function mapOf(TrueType $instance): array
    {
        (new \ReflectionMethod(TrueType::class, 'getCIDToGIDMap'))->invoke($instance);
        /** @var mixed $fdt */
        $fdt = (new \ReflectionProperty(TrueType::class, 'fdt'))->getValue($instance);
        $this->assertIsArray($fdt);
        /** @var mixed $ctg */
        $ctg = $fdt['ctgdata'] ?? null;
        $this->assertIsArray($ctg);

        /** @var array<int, int> $ctg */
        return $ctg;
    }

    /**
     * A font of 32 glyphs gets the smallest budget, 256 codes, which a segment covering
     * exactly that many codes fits.
     */
    public function testASegmentFillingTheBudgetIsMappedAlongWithTheTerminatingSegment(): void
    {
        $map = $this->mapOf($this->build($this->subtable(0xFF), 32));

        $this->assertCount(256, $map, 'the 256 codes of the segment are mapped');
        $this->assertSame(1, $map[0] ?? null);
        $this->assertSame(256, $map[0xFF] ?? null);
        // the terminating segment closes the table, it does not map the noncharacter U+FFFF
        $this->assertArrayNotHasKey(0xFFFF, $map);
    }

    /**
     * A segment covering more codes than the glyph count accounts for is still refused.
     */
    public function testASegmentPastTheBudgetIsRefused(): void
    {
        $instance = $this->build($this->subtable(0x0100), 32);

        $this->bcExpectException(\Com\Tecnick\Pdf\Font\Exception::class);
        (new \ReflectionMethod(TrueType::class, 'getCIDToGIDMap'))->invoke($instance);
    }
}
