<?php

/**
 * CmapSubtableBoundsTest.php
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
 * A cmap subtable declaring more entries than it holds must stop at the end of the cmap
 * table, rather than turn the bytes of the table that follows it into glyph indexes.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class CmapSubtableBoundsTest extends TestUtil
{
    /**
     * Bytes of the table that follows the cmap one. Every one of them would decode as a
     * glyph index, so reading any of them is visible in the resulting map.
     */
    private const NEXT_TABLE = "\x77\x77\x77\x77\x77\x77\x77\x77\x77\x77\x77\x77\x77\x77\x77\x77";

    /**
     * Build a TrueType importer over a synthetic font holding a single cmap subtable
     * followed by the bytes of another table.
     *
     * @param string $subtable   Subtable bytes, placed at offset 0 of the cmap table.
     * @param int    $cmapLength Declared length of the cmap table.
     */
    private function build(string $subtable, int $cmapLength): TrueType
    {
        $font = $subtable . self::NEXT_TABLE;
        $class = new \ReflectionClass(TrueType::class);
        $instance = $class->newInstanceWithoutConstructor();

        try {
            $byte = new Byte($font);
        } catch (\RangeException $exception) {
            $this->fail($exception->getMessage());
        }

        $fdt = \array_replace(Load::DEFAULT_DATA, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['checkSum' => 0, 'data' => '', 'offset' => 0, 'length' => $cmapLength]],
            'type' => 'TrueTypeUnicode',
        ]);

        (new \ReflectionProperty(TrueType::class, 'font'))->setValue($instance, $font);
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
        $this->invoke($instance, 'getCIDToGIDMap');
        /** @var mixed $fdt */
        $fdt = (new \ReflectionProperty(TrueType::class, 'fdt'))->getValue($instance);
        $this->assertIsArray($fdt);
        /** @var mixed $ctg */
        $ctg = $fdt['ctgdata'] ?? null;
        $this->assertIsArray($ctg);

        /** @var array<int, int> $ctg */
        return $ctg;
    }

    private function invoke(TrueType $instance, string $method): void
    {
        (new \ReflectionMethod(TrueType::class, $method))->invoke($instance);
    }

    /**
     * @param array<int, int> $map
     */
    private function glyphOf(array $map, int $code): int
    {
        $this->assertArrayHasKey($code, $map);

        return $map[$code] ?? 0;
    }

    /**
     * Format 0 always documents 256 glyph indexes. A table holding fewer of them stops
     * where it ends.
     */
    public function testFormat0StopsAtTheEndOfTheCmapTable(): void
    {
        $subtable =
            "\x00\x00" // format
            . "\x01\x06" // length (unused)
            . "\x00\x00" // language (unused)
            . "\x0A\x0B\x0C"; // three of the 256 documented glyph indexes
        $map = $this->mapOf($this->build($subtable, 9));

        $this->assertSame(10, $this->glyphOf($map, 0));
        $this->assertSame(11, $this->glyphOf($map, 1));
        $this->assertSame(12, $this->glyphOf($map, 2));
        // the fourth code would be the first byte of the next table
        $this->assertArrayNotHasKey(3, $map);
    }

    /**
     * Format 6 declares the size of its subrange, which a truncated or hostile table may
     * overstate.
     */
    public function testFormat6StopsAtTheEndOfTheCmapTable(): void
    {
        $subtable =
            "\x00\x06" // format
            . "\x00\x0F" // length (unused)
            . "\x00\x00" // language (unused)
            . "\x00\x41" // firstCode = 65
            . "\x00\x64" // entryCount = 100, of which the table holds two
            . "\x00\x0A"
            . "\x00\x0B";
        $map = $this->mapOf($this->build($subtable, 14));

        $this->assertSame(10, $this->glyphOf($map, 65));
        $this->assertSame(11, $this->glyphOf($map, 66));
        $this->assertArrayNotHasKey(67, $map);
        $this->assertNotContains(0x7777, $map);
    }

    /**
     * Format 10 declares the size of its covered range as a uint32.
     */
    public function testFormat10StopsAtTheEndOfTheCmapTable(): void
    {
        $subtable =
            "\x00\x0A" // format
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x00" // length (unused)
            . "\x00\x00\x00\x00" // language (unused)
            . \pack('N', 65) // startCharCode
            . \pack('N', 100) // numChars, of which the table holds two
            . "\x00\x0A"
            . "\x00\x0B";
        $map = $this->mapOf($this->build($subtable, 24));

        $this->assertSame(10, $this->glyphOf($map, 65));
        $this->assertSame(11, $this->glyphOf($map, 66));
        $this->assertArrayNotHasKey(67, $map);
        $this->assertNotContains(0x7777, $map);
    }

    /**
     * Format 4 declares its segment count in the header, and the four parallel arrays that
     * follow hold one entry per segment each, so the count is bounded by the table.
     */
    public function testFormat4SegmentArraysStopAtTheEndOfTheCmapTable(): void
    {
        $subtable =
            "\x00\x04" // format
            . "\x00\x20" // length
            . "\x00\x00" // language (unused)
            . "\xFF\xFE" // segCountX2 = 65534, so 32767 segments
            . "\x00\x00\x00\x00\x00\x00" // searchRange, entrySelector, rangeShift (unused)
            . "\x00\x42" // endCount[0] = 66
            . "\x00\x00" // reservedPad
            . "\x00\x41" // startCount[0] = 65
            . "\x00\x0A" // idDelta[0] = 10
            . "\x00\x00"; // idRangeOffset[0] = 0
        // room for exactly one segment: 8 bytes of arrays plus the reserved pad
        $map = $this->mapOf($this->build($subtable, 26));

        // the single segment the table carries is mapped through its idDelta
        $this->assertSame(75, $this->glyphOf($map, 65));
        $this->assertSame(76, $this->glyphOf($map, 66));
        $this->assertArrayNotHasKey(0x7777, $map);
        $this->assertNotContains(0x7777, $map);
    }

    /**
     * Format 8 declares its group count as a uint32.
     */
    public function testFormat8StopsAtTheEndOfTheCmapTable(): void
    {
        $subtable =
            "\x00\x08" // format
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x00" // length (unused)
            . "\x00\x00\x00\x00" // language (unused)
            . \str_repeat("\x00", 8192) // is32 array
            . \pack('N', 100) // numGroups, of which the table holds one
            . \pack('NNN', 65, 66, 10);
        $map = $this->mapOf($this->build($subtable, 8220));

        $this->assertSame(10, $this->glyphOf($map, 65));
        $this->assertSame(11, $this->glyphOf($map, 66));
        $this->assertArrayNotHasKey(67, $map);
        $this->assertNotContains(0x7777, $map);
    }

    /**
     * Format 12 declares its group count as a uint32.
     */
    public function testFormat12StopsAtTheEndOfTheCmapTable(): void
    {
        $subtable =
            "\x00\x0C" // format
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x00" // length (unused)
            . "\x00\x00\x00\x00" // language (unused)
            . \pack('N', 100) // numGroups, of which the table holds one
            . \pack('NNN', 65, 66, 10);
        $map = $this->mapOf($this->build($subtable, 28));

        $this->assertSame(10, $this->glyphOf($map, 65));
        $this->assertSame(11, $this->glyphOf($map, 66));
        $this->assertArrayNotHasKey(67, $map);
        $this->assertNotContains(0x7777, $map);
    }

    /**
     * Format 13 declares its group count as a uint32.
     */
    public function testFormat13StopsAtTheEndOfTheCmapTable(): void
    {
        $subtable =
            "\x00\x0D" // format
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x00" // length (unused)
            . "\x00\x00\x00\x00" // language (unused)
            . \pack('N', 100) // numGroups, of which the table holds one
            . \pack('NNN', 65, 66, 10);
        $map = $this->mapOf($this->build($subtable, 28));

        $this->assertSame(10, $this->glyphOf($map, 65));
        $this->assertSame(10, $this->glyphOf($map, 66));
        $this->assertArrayNotHasKey(67, $map);
        $this->assertNotContains(0x7777, $map);
    }

    /**
     * A table long enough for everything it declares is read in full: the clamp only ever
     * removes the entries the table does not carry.
     */
    public function testACompleteSubtableIsReadInFull(): void
    {
        $subtable =
            "\x00\x06" // format
            . "\x00\x0F" // length (unused)
            . "\x00\x00" // language (unused)
            . "\x00\x41" // firstCode = 65
            . "\x00\x03" // entryCount = 3
            . "\x00\x0A"
            . "\x00\x0B"
            . "\x00\x0C";
        $map = $this->mapOf($this->build($subtable, 16));

        $this->assertSame(10, $this->glyphOf($map, 65));
        $this->assertSame(11, $this->glyphOf($map, 66));
        $this->assertSame(12, $this->glyphOf($map, 67));
    }
}
