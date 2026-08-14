<?php

/**
 * SfntRecordCountTest.php
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
 * A declared record count is believed only as far as the bytes that can hold the records.
 *
 * checkTableBounds() proves that each table of the directory lies inside the file, and says
 * nothing about the counts stored inside those tables, so each count is clamped to the
 * number of records its table can hold.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class SfntRecordCountTest extends TestUtil
{
    /**
     * @param array<string, mixed> $fdt Font data overrides, merged over the defaults.
     */
    private function build(string $font, array $fdt): TrueType
    {
        $class = new \ReflectionClass(TrueType::class);
        $instance = $class->newInstanceWithoutConstructor();
        try {
            $byte = new Byte($font);
        } catch (\RangeException $exception) {
            $this->fail($exception->getMessage());
        }

        $this->setProperty($instance, 'font', $font);
        $this->setProperty($instance, 'fdt', \array_replace(\Com\Tecnick\Pdf\Font\Load::DEFAULT_DATA, $fdt));
        $this->setProperty($instance, 'fbyte', $byte);
        $this->setProperty($instance, 'offset', 0);

        return $instance;
    }

    private function setProperty(TrueType $instance, string $name, mixed $value): void
    {
        (new \ReflectionProperty(TrueType::class, $name))->setValue($instance, $value);
    }

    private function invoke(TrueType $instance, string $method): void
    {
        (new \ReflectionMethod(TrueType::class, $method))->invoke($instance);
    }

    /**
     * @return array<string, mixed>
     */
    private function fontDataOf(TrueType $instance): array
    {
        /** @var mixed $fdt */
        $fdt = (new \ReflectionProperty(TrueType::class, 'fdt'))->getValue($instance);
        $this->assertIsArray($fdt);

        /** @var array<string, mixed> $fdt */
        return $fdt;
    }

    /**
     * @return array<int, mixed>
     */
    private function encodingTablesOf(TrueType $instance): array
    {
        /** @var array<int, mixed> $tables */
        $tables = $this->arrayMember($this->fontDataOf($instance), 'encodingTables');

        return $tables;
    }

    /**
     * Assemble a cmap encoding record.
     */
    private function cmapRecord(int $platformID, int $encodingID, int $subtableOffset): string
    {
        return \pack('nnN', $platformID, $encodingID, $subtableOffset);
    }

    public function testTheCmapEncodingRecordCountIsBoundedByTheDeclaredLength(): void
    {
        // the header says 1000 records, the declared length leaves room for one
        $cmap = \pack('nn', 0, 1000) . $this->cmapRecord(3, 1, 8) . \str_repeat("\xBB", 512);
        $instance = $this->build($cmap, ['table' => ['cmap' => ['offset' => 0, 'length' => 12]]]);

        $this->invoke($instance, 'getEncodingTables');

        $this->assertCount(1, $this->encodingTablesOf($instance));
    }

    public function testCmapKeepsEveryRecord(): void
    {
        $cmap = \pack('nn', 0, 2) . $this->cmapRecord(3, 1, 16) . $this->cmapRecord(1, 0, 12);
        $instance = $this->build($cmap, ['table' => ['cmap' => ['offset' => 0, 'length' => 20]]]);

        $this->invoke($instance, 'getEncodingTables');

        $this->assertCount(2, $this->encodingTablesOf($instance));
    }

    /**
     * A record whose subtable does not fit the declared length of the cmap table is
     * discarded: following it would read the table that comes after the cmap one as a
     * character map.
     */
    public function testACmapRecordPointingOutsideTheTableIsDiscarded(): void
    {
        $cmap =
            \pack('nn', 0, 3)
            . $this->cmapRecord(3, 1, 28) // starts at the last four bytes of the table
            . $this->cmapRecord(1, 0, 29) // one byte past them
            . $this->cmapRecord(0, 3, 0xAAAA_AAAA) // far outside the font file
            . \str_repeat("\xBB", 512);
        $instance = $this->build($cmap, ['table' => ['cmap' => ['offset' => 0, 'length' => 32]]]);

        $this->invoke($instance, 'getEncodingTables');

        $this->assertSame([['platformID' => 3, 'encodingID' => 1, 'offset' => 28]], $this->encodingTablesOf($instance));
    }

    /**
     * A table shorter than its own header leaves room for no record at all.
     */
    public function testACmapShorterThanItsHeaderYieldsNoRecord(): void
    {
        $cmap = \pack('nn', 0, 1000) . \str_repeat("\xAA", 8);
        $instance = $this->build($cmap, ['table' => ['cmap' => ['offset' => 0, 'length' => 2]]]);

        $this->invoke($instance, 'getEncodingTables');

        $this->assertSame([], $this->encodingTablesOf($instance));
    }

    /**
     * The sfnt directory itself: a record is 16 bytes and follows the 12-byte header, so
     * the count is clamped to the number of records the file can hold.
     */
    public function testTheSfntTableCountIsBoundedByTheFileSize(): void
    {
        // sfnt version, numTables = 65535, searchRange/entrySelector/rangeShift, 12 spare
        $font = \pack('Nnnnn', 0x1_0000, 65_535, 0, 0, 0) . \str_repeat("\x00", 12);
        $instance = $this->build($font, []);
        // isValidType() has read the version, as it has when getTables() runs for real
        $this->setProperty($instance, 'offset', 4);

        $this->invoke($instance, 'getTables');

        // (24 - 12) / 16 = 0 complete records fit after the header of this stub
        $this->assertSame([], $this->arrayMember($this->fontDataOf($instance), 'table'));
    }

    public function testSfntDirectoryKeepsEveryTable(): void
    {
        $record = static fn(string $tag): string => $tag . \str_repeat("\x00", 12);
        $font = \pack('Nnnnn', 0x1_0000, 2, 0, 0, 0) . $record('cmap') . $record('head');
        $instance = $this->build($font, []);
        $this->setProperty($instance, 'offset', 4);

        $this->invoke($instance, 'getTables');

        $tables = $this->arrayMember($this->fontDataOf($instance), 'table');
        $this->assertSame(['cmap', 'head'], \array_keys($tables));
    }

    /**
     * A tag made of digits would be stored as an integer array key, which is not the shape
     * the directory declares and would make the ordering of the emitted subset undefined.
     * No table this library reads carries such a tag, so the record is dropped and the
     * records that follow it are still read.
     */
    public function testSfntDirectoryDropsANumericTableTag(): void
    {
        $record = static fn(string $tag): string => $tag . \str_repeat("\x00", 12);
        $font =
            \pack('Nnnnn', 0x1_0000, 4, 0, 0, 0)
            . $record('1234') // decimal digits: an integer key
            . $record('cmap')
            . $record('0012') // leading zeroes: PHP keeps this one a string key
            . $record('head');
        $instance = $this->build($font, []);
        $this->setProperty($instance, 'offset', 4);

        $this->invoke($instance, 'getTables');

        $tables = $this->arrayMember($this->fontDataOf($instance), 'table');
        $this->assertSame(['cmap', '0012', 'head'], \array_keys($tables));
    }

    /**
     * The 'name' table is bounded the same way (12-byte records after a 6-byte header), and
     * the record that does fit is still read.
     */
    public function testAnInflatedNameCountStillReadsTheRecordThatFits(): void
    {
        // format, count = 1000, stringStorageOffset = 18
        // record 0: platformID 1, encodingID 0, languageID 0, nameID 1 (family), len 4, off 0
        $name = \pack('nnn', 0, 1000, 18) . \pack('nnnnnn', 1, 0, 0, 1, 4, 0) . 'GOOD';
        $instance = $this->build($name, ['table' => ['name' => ['offset' => 0, 'length' => 22]]]);

        $this->invoke($instance, 'getFontName');

        $this->assertSame('GOOD', $this->stringMember($this->fontDataOf($instance), 'name'));
    }
}
