<?php

/**
 * SubsetTest.php
 *
 * @since     2026-05-01
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

/**
 * Subset Test
 *
 * @since     2026-05-01
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class SubsetTest extends TestUtil
{
    private function setProp(object $obj, string $name, mixed $value): void
    {
        $prop = new \ReflectionProperty($obj, $name);
        $prop->setValue($obj, $value);
    }

    /** @return array<array-key, mixed> */
    private function getDefaultFdt(): array
    {
        $ref = new \ReflectionClass(\Com\Tecnick\Pdf\Font\Subset::class);
        $prop = $ref->getProperty('fdt');
        $instance = $ref->newInstanceWithoutConstructor();

        return (static fn(mixed $value): array => \is_array($value) ? $value : [])($prop->getValue($instance));
    }

    /**
     * @param array<array-key, mixed> $table
     *
     * @return array<array-key, mixed>
     */
    private function getTableRecord(array $table, string $tag): array
    {
        if (!isset($table[$tag]) || !\is_array($table[$tag])) {
            $this->fail('Missing or invalid table record: ' . $tag);
        }

        return $table[$tag];
    }

    /** @param array<array-key, mixed> $table */
    private function getTableRecordString(array $table, string $tag, string $field): string
    {
        $record = $this->getTableRecord($table, $tag);
        if (!isset($record[$field]) || !\is_string($record[$field])) {
            $this->fail('Missing or invalid table string field: ' . $tag . '.' . $field);
        }

        return $record[$field];
    }

    /** @param array<array-key, mixed> $table */
    private function getTableRecordInt(array $table, string $tag, string $field): int
    {
        $record = $this->getTableRecord($table, $tag);
        if (!isset($record[$field]) || !\is_int($record[$field])) {
            $this->fail('Missing or invalid table int field: ' . $tag . '.' . $field);
        }

        return $record[$field];
    }

    /** @throws \Com\Tecnick\Pdf\Font\Exception */
    public function testTableChecksumPadsTrailingBytes(): void
    {
        $subset = new class() extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct() {}

            /** @throws \Com\Tecnick\Pdf\Font\Exception */
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
        $subset = new class() extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct() {}

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

    /** @throws \Com\Tecnick\Pdf\Font\Exception */
    public function testRemoveUnusedTablesDropsUnknownTableTags(): void
    {
        // Build an anonymous subclass that exposes removeUnusedTables and lets us
        // inspect the resulting fdt without running the full constructor chain.
        $subset = new class() extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct() {}

            /** @throws \Com\Tecnick\Pdf\Font\Exception */
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

        // A font binary with 8 bytes of head data (for substr to not be empty)
        $font = str_repeat("\x00", 64);
        $this->setProp($subset, 'font', $font);
        $this->setProp($subset, 'offset', 12);

        // Provide two tables: 'head' (known → kept) and 'xxxx' (unknown → removed)
        $this->setProp($subset, 'fdt', array_merge($this->getDefaultFdt(), [
            'table' => [
                'head' => ['offset' => 0, 'length' => 8, 'checkSum' => 0, 'data' => ''],
                'xxxx' => ['offset' => 0, 'length' => 8, 'checkSum' => 0, 'data' => ''],
            ],
        ]));

        $subset->run();

        $table = $subset->getTable();
        $this->assertArrayHasKey('head', $table);
        $this->assertArrayNotHasKey('xxxx', $table);
    }

    /**
     * Build a Subset harness exposing removeUnusedTables over a font buffer and tables.
     *
     * @param array<string, array{'checkSum': int, 'data': string, 'length': int, 'offset': int}> $tables
     */
    private function buildRemoveUnusedTablesStub(string $font, array $tables): SubsetTestHarness
    {
        $subset = new SubsetTestHarness();
        $this->setProp($subset, 'font', $font);
        $this->setProp($subset, 'offset', 12);
        $this->setProp($subset, 'fdt', array_merge($this->getDefaultFdt(), ['table' => $tables]));

        return $subset;
    }

    /**
     * The emitted table directory advertises the declared length, so a table whose bytes
     * run past the end of the font file is rejected.
     */
    public function testRemoveUnusedTablesRejectsATableTruncatedByTheFontFile(): void
    {
        // the font holds 16 bytes but 'hhea' claims 64 starting at offset 8
        $subset = $this->buildRemoveUnusedTablesStub(str_repeat("\x00", 16), [
            'hhea' => ['offset' => 8, 'length' => 64, 'checkSum' => 0, 'data' => ''],
        ]);

        try {
            $subset->runRemoveUnusedTables();
        } catch (\Com\Tecnick\Pdf\Font\Exception $exception) {
            $this->assertStringContainsString('truncated font table: hhea', $exception->getMessage());
            return;
        }

        $this->fail('a truncated table must be rejected');
    }

    public function testRemoveUnusedTablesRejectsATableStartingPastTheEndOfTheFont(): void
    {
        $subset = $this->buildRemoveUnusedTablesStub(str_repeat("\x00", 16), [
            'hhea' => ['offset' => 128, 'length' => 4, 'checkSum' => 0, 'data' => ''],
        ]);

        try {
            $subset->runRemoveUnusedTables();
        } catch (\Com\Tecnick\Pdf\Font\Exception $exception) {
            $this->assertStringContainsString('truncated font table: hhea', $exception->getMessage());
            return;
        }

        $this->fail('a table beyond the end of the font must be rejected');
    }

    /** @throws \Com\Tecnick\Pdf\Font\Exception */
    public function testRemoveUnusedTablesAcceptsATableThatFitsExactly(): void
    {
        $subset = $this->buildRemoveUnusedTablesStub(str_repeat("\xAB", 16), [
            'hhea' => ['offset' => 8, 'length' => 8, 'checkSum' => 0, 'data' => ''],
        ]);

        $subset->runRemoveUnusedTables();

        $table = $subset->getTable();
        $this->assertArrayHasKey('hhea', $table);
        // 8 bytes read, and 8 is already a multiple of 4 so no padding is appended
        $this->assertSame(str_repeat("\xAB", 8), $this->getTableRecordString($table, 'hhea', 'data'));
        $this->assertSame(8, $this->getTableRecordInt($table, 'hhea', 'length'));
    }

    /**
     * loca and glyf are rebuilt from the subset glyphs, so their declared length does not
     * describe the original file and is not checked against it.
     *
     * @throws \Com\Tecnick\Pdf\Font\Exception
     */
    public function testRemoveUnusedTablesDoesNotLengthCheckTheRebuiltTables(): void
    {
        $subset = $this->buildRemoveUnusedTablesStub(str_repeat("\x00", 16), [
            'glyf' => ['offset' => 0, 'length' => 4096, 'checkSum' => 0, 'data' => 'rebuilt'],
            'loca' => ['offset' => 0, 'length' => 2048, 'checkSum' => 0, 'data' => 'rebuilt'],
        ]);

        $subset->runRemoveUnusedTables();

        $table = $subset->getTable();
        $this->assertArrayHasKey('glyf', $table);
        $this->assertArrayHasKey('loca', $table);
    }

    // -------------------------------------------------------------------------
    // addProcessedTables
    // -------------------------------------------------------------------------

    /** @throws \Com\Tecnick\Pdf\Font\Exception */
    public function testAddProcessedTablesBuildsLocaAndGlyfFromSubsetGlyphs(): void
    {
        $subset = new class() extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct() {}

            /** @throws \Com\Tecnick\Pdf\Font\Exception */
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

        // Font data: 12 bytes (glyf table at offset 0, 12 bytes of raw glyph data)
        $font = str_repeat("\xAB", 12);
        $this->setProp($subset, 'font', $font);
        $this->setProp($subset, 'offset', 0);

        // subglyphs: only glyph 0 is in the subset
        $this->setProp($subset, 'subglyphs', [0 => true]);

        // Inject the fdt state the method needs
        $this->setProp($subset, 'fdt', array_merge($this->getDefaultFdt(), [
            'tot_num_glyphs' => 2,
            'short_offset' => false, // long (Offset32) loca entries
            'indexToLoc' => [0 => 0, 1 => 8], // glyph 0 is 8 bytes
            'table' => [
                'glyf' => ['offset' => 0, 'length' => 12, 'checkSum' => 0, 'data' => ''],
                'loca' => ['offset' => 0, 'length' => 0, 'checkSum' => 0, 'data' => ''],
            ],
        ]));

        $subset->run();

        $table = $subset->getTable();

        // loca must have been rebuilt
        $this->assertNotEmpty($this->getTableRecordString($table, 'loca', 'data'));
        // glyf must contain the extracted glyph bytes (8 bytes for glyph 0, padded to multiple of 4)
        $this->assertNotEmpty($this->getTableRecordString($table, 'glyf', 'data'));
        // The checksum must have been computed (non-zero for non-empty data)
        $this->assertNotSame(
            0,
            $this->getTableRecordInt($table, 'loca', 'checkSum') + $this->getTableRecordInt($table, 'glyf', 'checkSum'),
        );
    }

    /** @throws \Com\Tecnick\Pdf\Font\Exception */
    public function testBuildSubsetFontKeepsTableDirectoryOffsetsIntact(): void
    {
        $subset = new class() extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct() {}

            /** @throws \Com\Tecnick\Pdf\Font\Exception */
            public function run(): void
            {
                $this->buildSubsetFont();
            }

            public function getSubFont(): string
            {
                return $this->subfont;
            }
        };

        $this->setProp($subset, 'fdt', array_merge($this->getDefaultFdt(), [
            'table' => [
                // Offsets in fdt are relative to the 12-byte sfnt header.
                'head' => [
                    'checkSum' => 0,
                    'data' => str_repeat("\x00", 12),
                    'length' => 12,
                    'offset' => 12,
                ],
            ],
        ]));

        $subset->run();
        $subfont = $subset->getSubFont();

        // With a single table, the table directory offset must point to byte 28
        // (12-byte sfnt header + 16-byte table record).
        $offset = \unpack('N', \substr($subfont, 20, 4));
        $this->assertIsArray($offset);
        $this->assertSame(28, $offset[1]);
    }

    /** @throws \Com\Tecnick\Pdf\Font\Exception */
    public function testAddProcessedTablesUsesNextAvailableLocaIndexWhenImmediateIsMissing(): void
    {
        $subset = new class() extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct() {}

            /** @throws \Com\Tecnick\Pdf\Font\Exception */
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

        // 8 bytes for glyph 0 followed by 8 bytes for glyph 1.
        $font = str_repeat("\xAB", 16);
        $this->setProp($subset, 'font', $font);
        $this->setProp($subset, 'offset', 0);
        $this->setProp($subset, 'subglyphs', [0 => true]);

        // Parser output where index 1 was removed as a duplicate-empty marker, so glyph 0
        // uses index 2 as the closing boundary.
        $this->setProp($subset, 'fdt', array_merge($this->getDefaultFdt(), [
            'tot_num_glyphs' => 3,
            'short_offset' => false,
            'indexToLoc' => [0 => 0, 2 => 8, 3 => 16],
            'table' => [
                'glyf' => ['offset' => 0, 'length' => 16, 'checkSum' => 0, 'data' => ''],
                'loca' => ['offset' => 0, 'length' => 0, 'checkSum' => 0, 'data' => ''],
            ],
        ]));

        $subset->run();
        $table = $subset->getTable();

        // glyph 0 is kept although index 1 is missing
        $this->assertNotEmpty($this->getTableRecordString($table, 'glyf', 'data'));
    }

    public function testAddCompositeGlyphsPreservesNumericGlyphIndexes(): void
    {
        $subset = new class() extends \Com\Tecnick\Pdf\Font\Subset {
            private bool $added = false;

            public function __construct() {}

            public function run(): void
            {
                $this->addCompositeGlyphs();
            }

            /** @return array<int, bool> */
            public function getSubglyphs(): array
            {
                return $this->subglyphs;
            }

            /**
             * @param array<int, bool> $new_sga
             * @param int              $key
             *
             * @return array<int, bool>
             */
            protected function findCompositeGlyphs(array $new_sga, int $key): array
            {
                if (!$this->added) {
                    $this->added = true;
                    $new_sga[3283] = true;
                }

                return $new_sga;
            }
        };

        $this->setProp($subset, 'subglyphs', [853 => true]);

        $subset->run();
        $subglyphs = $subset->getSubglyphs();

        $this->assertArrayHasKey(853, $subglyphs);
        $this->assertArrayHasKey(3283, $subglyphs);
    }

    public function testAddCompositeGlyphsSkipsDisabledDerivedGlyphs(): void
    {
        $subset = new class() extends \Com\Tecnick\Pdf\Font\Subset {
            private bool $first = true;

            public function __construct() {}

            public function run(): void
            {
                $this->addCompositeGlyphs();
            }

            /** @return array<int, bool> */
            public function getSubglyphs(): array
            {
                return $this->subglyphs;
            }

            /**
             * @param array<int, bool> $new_sga
             * @param int              $key
             *
             * @return array<int, bool>
             */
            protected function findCompositeGlyphs(array $new_sga, int $key): array
            {
                if ($this->first) {
                    $this->first = false;
                    $new_sga[400] = false;
                    $new_sga[401] = true;
                }

                return $new_sga;
            }
        };

        $this->setProp($subset, 'subglyphs', [100 => true]);
        $subset->run();

        $subglyphs = $subset->getSubglyphs();
        $this->assertArrayHasKey(100, $subglyphs);
        $this->assertArrayHasKey(401, $subglyphs);
        $this->assertArrayNotHasKey(400, $subglyphs);
    }

    public function testFindCompositeGlyphsParsesScaleAndTwoByTwoComponents(): void
    {
        $subset = new class() extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct() {}

            /**
             * @param array<int, bool> $newSga
             *
             * @return array<int, bool>
             */
            public function runFindCompositeGlyphs(array $newSga, int $key): array
            {
                return $this->findCompositeGlyphs($newSga, $key);
            }
        };

        // Glyph header: numberOfContours = -1 (composite), 8 bytes bbox.
        // Component 1: MORE_COMPONENTS + WE_HAVE_AN_X_AND_Y_SCALE, glyph 5.
        // Component 2: WE_HAVE_A_TWO_BY_TWO, glyph 6.
        $font =
            "\xFF\xFF\x00\x00\x00\x00\x00\x00\x00\x00"
            . "\x00\x60\x00\x05\x00\x00\x00\x00\x00\x00"
            . "\x00\x80\x00\x06\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";

        $this->setProp($subset, 'font', $font);
        $this->setProp($subset, 'fbyte', new \Com\Tecnick\File\Byte($font));
        $this->setProp($subset, 'subglyphs', []);
        $this->setProp($subset, 'fdt', \array_replace_recursive($this->getDefaultFdt(), [
            'indexToLoc' => [0 => 0],
            'table' => [
                'glyf' => ['offset' => 0, 'length' => \strlen($font), 'checkSum' => 0, 'data' => ''],
            ],
        ]));

        $newSga = $subset->runFindCompositeGlyphs([], 0);
        $this->assertArrayHasKey(5, $newSga);
        $this->assertArrayHasKey(6, $newSga);
    }

    /** @throws \Com\Tecnick\Pdf\Font\Exception */
    public function testAddProcessedTablesCreatesShortLocaAndPadsTables(): void
    {
        $subset = new class() extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct() {}

            /** @throws \Com\Tecnick\Pdf\Font\Exception */
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

        // One glyph with 3 bytes to force padding in both loca and glyf tables.
        $font = "\xAA\xBB\xCC";
        $this->setProp($subset, 'font', $font);
        $this->setProp($subset, 'offset', 0);
        $this->setProp($subset, 'subglyphs', [0 => true]);
        $this->setProp($subset, 'fdt', \array_replace_recursive($this->getDefaultFdt(), [
            'tot_num_glyphs' => 1,
            'short_offset' => true,
            'indexToLoc' => [0 => 0, 1 => 3],
            'table' => [
                'glyf' => ['offset' => 0, 'length' => 3, 'checkSum' => 0, 'data' => ''],
            ],
        ]));

        $subset->run();
        $table = $subset->getTable();

        $this->assertArrayHasKey('loca', $table);
        // the directory declares the real length of each table, while the emitted bytes
        // are padded to the next four byte boundary
        $this->assertSame(2, $this->getTableRecordInt($table, 'loca', 'length'));
        $this->assertSame(3, $this->getTableRecordInt($table, 'glyf', 'length'));
        $this->assertSame(4, \strlen($this->getTableRecordString($table, 'loca', 'data')));
        $this->assertSame(4, \strlen($this->getTableRecordString($table, 'glyf', 'data')));
    }

    /**
     * The short format stores the glyph offsets halved as 16-bit values, so the last one it
     * reaches is 0xFFFF * 2 and a larger one is refused.
     *
     * @throws \Throwable
     */
    public function testAddProcessedTablesRejectsAnOffsetTheShortLocaCannotHold(): void
    {
        $subset = new class() extends \Com\Tecnick\Pdf\Font\Subset {
            public function __construct() {}

            /** @throws \Com\Tecnick\Pdf\Font\Exception */
            public function run(): void
            {
                $this->addProcessedTables();
            }
        };

        // A single glyph two bytes past 0xFFFF * 2, the largest offset the format holds,
        // so the second entry of the loca table cannot be written.
        $length = 131_072;
        $this->setProp($subset, 'font', \str_repeat("\x00", $length));
        $this->setProp($subset, 'offset', 0);
        $this->setProp($subset, 'subglyphs', [0 => true, 1 => true]);
        $this->setProp($subset, 'fdt', \array_replace_recursive($this->getDefaultFdt(), [
            'tot_num_glyphs' => 2,
            'short_offset' => true,
            'indexToLoc' => [0 => 0, 1 => $length, 2 => $length],
            'table' => [
                'glyf' => ['offset' => 0, 'length' => $length, 'checkSum' => 0, 'data' => ''],
            ],
        ]));

        $this->assertThrowsMessage(
            \Com\Tecnick\Pdf\Font\Exception::class,
            'too large for a short loca table',
            /** @throws \Throwable */
            static function () use ($subset): void {
                $subset->run();
            },
        );
    }
}
