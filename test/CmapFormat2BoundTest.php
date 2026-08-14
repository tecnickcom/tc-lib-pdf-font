<?php

/**
 * CmapFormat2BoundTest.php
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

/**
 * The glyph index array of a format 2 cmap stops at the end of the cmap table.
 *
 * The array is clamped with 'min(cmapEnd, strlen(font))', as format 4 does, so the entry
 * counts the sub-headers declare cannot pull the bytes of whatever table follows the cmap
 * in as glyph indexes.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class CmapFormat2BoundTest extends TestUtil
{
    /**
     * Build a format 2 subtable whose single sub-header claims $entryCount glyph indexes.
     *
     * All 256 high bytes map to sub-header 0, so the codes are single-byte ones. The
     * sub-header covers codes 0..entryCount-1 and reads them from the shared glyph index
     * array that follows it.
     */
    private function subtable(int $entryCount): string
    {
        return (
            \pack('n', 2) // format
            . \pack('n', 536) // length, unused by the parser
            . \pack('n', 0) // language
            . \str_repeat("\x00\x00", 256) // subHeaderKeys, all sub-header 0
            . \pack('n', 0) // firstCode
            . \pack('n', $entryCount)
            . \pack('n', 0) // idDelta
            . \pack('n', 2) // idRangeOffset, rebased to the start of the glyph index array
        );
    }

    /**
     * @param array<string, mixed> $table
     *
     * @return array<int, int>
     */
    private function ctgFor(string $font, array $table): array
    {
        $class = new \ReflectionClass(TrueType::class);
        $instance = $class->newInstanceWithoutConstructor();
        try {
            $byte = new Byte($font);
        } catch (\RangeException $exception) {
            $this->fail($exception->getMessage());
        }

        $fdt = \array_replace(\Com\Tecnick\Pdf\Font\Load::DEFAULT_DATA, [
            'encodingTables' => [['platformID' => 3, 'encodingID' => 1, 'offset' => 0]],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => $table,
            'type' => 'TrueTypeUnicode',
        ]);

        (new \ReflectionProperty(TrueType::class, 'font'))->setValue($instance, $font);
        (new \ReflectionProperty(TrueType::class, 'fdt'))->setValue($instance, $fdt);
        (new \ReflectionProperty(TrueType::class, 'fbyte'))->setValue($instance, $byte);
        (new \ReflectionProperty(TrueType::class, 'offset'))->setValue($instance, 0);

        (new \ReflectionMethod(TrueType::class, 'getCIDToGIDMap'))->invoke($instance);

        /** @var mixed $result */
        $result = (new \ReflectionProperty(TrueType::class, 'fdt'))->getValue($instance);
        $this->assertIsArray($result);

        /** @var array<string, mixed> $result */
        return $this->intMap($this->arrayMember($result, 'ctgdata'));
    }

    /**
     * The bytes that follow the cmap belong to the next table, and are not glyph indexes.
     */
    public function testTheArrayStopsAtTheEndOfTheCmapTable(): void
    {
        // two entries are inside the declared cmap, the third is the table after it
        $cmap = $this->subtable(3) . \pack('nn', 99, 100);
        $trailing = \pack('n', 101); // would read as the glyph of code 2
        $ctg = $this->ctgFor($cmap . $trailing, [
            'cmap' => ['offset' => 0, 'length' => \strlen($cmap)],
        ]);

        $this->assertSame(99, $ctg[0] ?? null);
        $this->assertSame(100, $ctg[1] ?? null);
        // the code the array no longer covers falls back to notdef
        $this->assertSame(0, $ctg[2] ?? null);
    }

    /**
     * The sub-headers are read from the cmap table too: a table with room for fewer of them
     * than the keys address leaves the high bytes keyed to the missing ones unmapped.
     */
    public function testTheSubHeadersStopAtTheEndOfTheCmapTable(): void
    {
        // every high byte selects sub-header 0, except 0x05 which selects sub-header 1
        $keys = \str_repeat("\x00\x00", 256);
        $keys = \substr_replace($keys, \pack('n', 8), 10, 2);
        $cmap =
            \pack('n', 2) // format
            . \pack('n', 532) // length, unused by the parser
            . \pack('n', 0) // language
            . $keys
            // sub-header 0: single-byte codes 0..2, read from the glyph index array
            . \pack('n', 0) // firstCode
            . \pack('n', 3) // entryCount
            . \pack('n', 0) // idDelta
            . \pack('n', 2) // idRangeOffset, rebased to the start of the glyph index array
            . \pack('nnn', 99, 100, 101);
        // the table stops right after the first sub-header and its glyph index array
        $trailing = \pack('nnnn', 0, 3, 0, 2); // would read as sub-header 1
        $ctg = $this->ctgFor($cmap . $trailing, [
            'cmap' => ['offset' => 0, 'length' => \strlen($cmap)],
        ]);

        $this->assertSame(99, $ctg[0] ?? null);
        $this->assertSame(100, $ctg[1] ?? null);
        $this->assertSame(101, $ctg[2] ?? null);
        // the two-byte codes of the high byte keyed to the missing sub-header
        $this->assertArrayNotHasKey(0x0500, $ctg);
        $this->assertArrayNotHasKey(0x0501, $ctg);
        $this->assertArrayNotHasKey(0x0502, $ctg);
    }

    /**
     * The array of high byte keys is read from the cmap table as well, so a table with room
     * for fewer than the 256 the format documents stops where it ends instead of reading
     * past the end of the font file.
     */
    public function testTheKeyArrayStopsAtTheEndOfTheCmapTable(): void
    {
        // room for ten keys and nothing else, and no byte after the table
        $cmap = \pack('n', 2) . \pack('n', 26) . \pack('n', 0) . \str_repeat("\x00\x00", 10);
        $ctg = $this->ctgFor($cmap, [
            'cmap' => ['offset' => 0, 'length' => \strlen($cmap)],
        ]);

        // no sub-header fits either, so only the notdef entry the caller adds is left
        $this->assertSame([0 => 0], $ctg);
    }

    /**
     * A subtable that stays inside its table is read in full.
     */
    public function testEveryEntryInsideTheTableIsRead(): void
    {
        $cmap = $this->subtable(3) . \pack('nnn', 99, 100, 101);
        $ctg = $this->ctgFor($cmap, [
            'cmap' => ['offset' => 0, 'length' => \strlen($cmap)],
        ]);

        $this->assertSame(99, $ctg[0] ?? null);
        $this->assertSame(100, $ctg[1] ?? null);
        $this->assertSame(101, $ctg[2] ?? null);
    }
}
