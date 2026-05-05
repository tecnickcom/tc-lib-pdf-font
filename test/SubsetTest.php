<?php

/**
 * SubsetTest.php
 *
 * @since     2026-05-01
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Test;

/**
 * Subset Test
 *
 * @since     2026-05-01
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class SubsetTest extends TestUtil
{
    public function testTableChecksumPadsTrailingBytes(): void
    {
        $subset = new class () extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct()
            {
            }

            public function checksum(string $table, int $length): int
            {
                return $this->getTableChecksum($table, $length);
            }
        };

        // 3-byte table must be zero-padded to a 4-byte word before summing.
        $this->assertSame(0x01020300, $subset->checksum("\x01\x02\x03", 3));
    }

    public function testTableChecksumHandlesMixedFullAndPartialWords(): void
    {
        $subset = new class () extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct()
            {
            }

            public function checksum(string $table, int $length): int
            {
                return $this->getTableChecksum($table, $length);
            }
        };

        $table = "\x11\x22\x33\x44\x55\x66\x77";
        // 0x11223344 + 0x55667700, modulo 2^32
        $this->assertSame(0x6688AA44, $subset->checksum($table, 7));
    }

    // -------------------------------------------------------------------------
    // removeUnusedTables
    // -------------------------------------------------------------------------

    public function testRemoveUnusedTablesDropsUnknownTableTags(): void
    {
        // Build an anonymous subclass that exposes removeUnusedTables and lets us
        // inspect the resulting fdt without running the full constructor chain.
        $subset = new class () extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct()
            {
            }

            public function run(): void
            {
                $this->removeUnusedTables();
            }

            /** @return array<string, mixed> */
            public function getTable(): array
            {
                return $this->fdt['table'];
            }
        };

        // Use reflection to inject the minimum state the method needs.
        $setProp = static function (object $obj, string $name, mixed $value): void {
            $prop = new \ReflectionProperty($obj, $name);
            $prop->setAccessible(true);
            $prop->setValue($obj, $value);
        };

        // A font binary with 8 bytes of head data (for substr to not be empty)
        $font = str_repeat("\x00", 64);
        $setProp($subset, 'font', $font);
        $setProp($subset, 'offset', 12);

        // Provide two tables: 'head' (known → kept) and 'xxxx' (unknown → removed)
        $setProp($subset, 'fdt', array_merge(
            (function (): array {
                $ref  = new \ReflectionClass(\Com\Tecnick\Pdf\Font\Subset::class);
                $prop = $ref->getProperty('fdt');
                $prop->setAccessible(true);
                return $prop->getValue($ref->newInstanceWithoutConstructor());
            })(),
            [
                'table' => [
                    'head' => ['offset' => 0, 'length' => 8, 'checkSum' => 0, 'data' => ''],
                    'xxxx' => ['offset' => 0, 'length' => 8, 'checkSum' => 0, 'data' => ''],
                ],
            ]
        ));

        $subset->run();

        $table = $subset->getTable();
        $this->assertArrayHasKey('head', $table);
        $this->assertArrayNotHasKey('xxxx', $table);
    }

    // -------------------------------------------------------------------------
    // addProcessedTables
    // -------------------------------------------------------------------------

    public function testAddProcessedTablesBuildsLocaAndGlyfFromSubsetGlyphs(): void
    {
        $subset = new class () extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct()
            {
            }

            public function run(): void
            {
                $this->addProcessedTables();
            }

            /** @return array<string, mixed> */
            public function getTable(): array
            {
                return $this->fdt['table'];
            }
        };

        $setProp = static function (object $obj, string $name, mixed $value): void {
            $prop = new \ReflectionProperty($obj, $name);
            $prop->setAccessible(true);
            $prop->setValue($obj, $value);
        };

        // Font data: 12 bytes (glyf table at offset 0, 12 bytes of raw glyph data)
        $font = str_repeat("\xAB", 12);
        $setProp($subset, 'font', $font);
        $setProp($subset, 'offset', 0);

        // subglyphs: only glyph 0 is in the subset
        $setProp($subset, 'subglyphs', [0 => true]);

        // Inject the fdt state the method needs
        $setProp($subset, 'fdt', array_merge(
            (function (): array {
                $ref  = new \ReflectionClass(\Com\Tecnick\Pdf\Font\Subset::class);
                $prop = $ref->getProperty('fdt');
                $prop->setAccessible(true);
                return $prop->getValue($ref->newInstanceWithoutConstructor());
            })(),
            [
                'tot_num_glyphs' => 2,
                'short_offset'   => false,  // long (Offset32) loca entries
                'indexToLoc'     => [0 => 0, 1 => 8],  // glyph 0 is 8 bytes
                'table'          => [
                    'glyf' => ['offset' => 0, 'length' => 12, 'checkSum' => 0, 'data' => ''],
                    'loca' => ['offset' => 0, 'length' => 0,  'checkSum' => 0, 'data' => ''],
                ],
            ]
        ));

        $subset->run();

        $table = $subset->getTable();

        // loca must have been rebuilt
        $this->assertNotEmpty($table['loca']['data']);
        // glyf must contain the extracted glyph bytes (8 bytes for glyph 0, padded to multiple of 4)
        $this->assertNotEmpty($table['glyf']['data']);
        // The checksum must have been computed (non-zero for non-empty data)
        $this->assertNotSame(0, $table['loca']['checkSum'] + $table['glyf']['checkSum']);
    }
}
