<?php

/**
 * TrueTypeTest.php
 *
 * @since     2011-05-23
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
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Import\TrueType;

/**
 * TrueType Test
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @phpstan-import-type TFontData from \Com\Tecnick\Pdf\Font\Load
 */
class TrueTypeTest extends TestUtil
{
    public function testGetCIDToGIDMapFormat13SetsNotDefGlyph(): void
    {
        // Format 13 subtable with numGroups=0: maps nothing, only .notdef fallback is added.
        $font =
            "\x00\x0d" // format = 13
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x10" // length = 16
            . "\x00\x00\x00\x00" // language
            . "\x00\x00\x00\x00"; // numGroups = 0

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                [
                    'platformID' => 3,
                    'encodingID' => 1,
                    'offset' => 0,
                ],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => [
                'cmap' => [
                    'offset' => 0,
                ],
            ],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame([0 => 0], $this->getCtgData($fontData));
        $this->assertSame('TrueTypeUnicode', $this->getFontDataString($fontData, 'type'));
    }

    public function testProcessFormat13MapsCharsToSingleGlyph(): void
    {
        // Binary blob for a Format 13 subtable read after the format field (offset=2):
        //   reserved    : \x00\x00           (2 bytes)
        //   length      : \x00\x00\x00\x1c   (4 bytes, unused)
        //   language    : \x00\x00\x00\x00   (4 bytes, unused)
        //   numGroups   : \x00\x00\x00\x01   (4 bytes → 1 group)
        //   startChar   : \x00\x00\x00\x41   (4 bytes → 65 = 'A')
        //   endChar     : \x00\x00\x00\x43   (4 bytes → 67 = 'C')
        //   glyphID     : \x00\x00\x00\x05   (4 bytes → glyph 5)
        $font =
            "\x00\x0d" // format = 13
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x1c" // length
            . "\x00\x00\x00\x00" // language
            . "\x00\x00\x00\x01" // numGroups = 1
            . "\x00\x00\x00\x41" // startCharCode = 65
            . "\x00\x00\x00\x43" // endCharCode   = 67
            . "\x00\x00\x00\x05"; // glyphID       = 5

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                [
                    'platformID' => 3,
                    'encodingID' => 1,
                    'offset' => 0,
                ],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => [
                'cmap' => [
                    'offset' => 0,
                ],
            ],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        // Characters 65, 66 and 67 must all map to glyph 5 (many-to-one)
        $this->assertSame(5, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(5, $this->getCtgGlyph($fontData, 66));
        $this->assertSame(5, $this->getCtgGlyph($fontData, 67));
        // .notdef fallback must be present
        $this->assertSame(0, $this->getCtgGlyph($fontData, 0));
    }

    public function testProcessFormat13AddsGlyphsToSubset(): void
    {
        $font =
            "\x00\x0d"
            . "\x00\x00"
            . "\x00\x00\x00\x1c"
            . "\x00\x00\x00\x00"
            . "\x00\x00\x00\x01"
            . "\x00\x00\x00\x41" // startCharCode = 65
            . "\x00\x00\x00\x41" // endCharCode   = 65
            . "\x00\x00\x00\x07"; // glyphID       = 7

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                [
                    'platformID' => 3,
                    'encodingID' => 1,
                    'offset' => 0,
                ],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => [
                'cmap' => [
                    'offset' => 0,
                ],
            ],
            'type' => 'TrueTypeUnicode',
        ]);

        // Mark char 65 as a subset char
        $this->setProperty($instance, 'subchars', [65 => true]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);
        $subGlyphs = $this->getSubglyphs($instance);

        $this->assertSame(7, $this->getCtgGlyph($fontData, 65));
        $this->assertArrayHasKey(7, $subGlyphs);
        $this->assertTrue($subGlyphs[7] ?? false);
    }

    /**
     * A format 14 subtable maps Unicode Variation Sequences, not characters, so it is
     * refused as the character map of the font.
     */
    public function testGetCIDToGIDMapRejectsFormat14AsTheCharacterMap(): void
    {
        $font =
            "\x00\x0e" // format = 14
            . "\x00\x00\x00\x0a" // length = 10
            . "\x00\x00\x00\x00"; // numVarSelectorRecords = 0

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                [
                    'platformID' => 3,
                    'encodingID' => 1,
                    'offset' => 0,
                ],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => [
                'cmap' => [
                    'offset' => 0,
                ],
            ],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->assertThrowsMessage(
            FontException::class,
            'cmap format 14 is a supplementary',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'getCIDToGIDMap'),
        );
    }

    public function testGetCIDToGIDMapThrowsOnUnsupportedFormat(): void
    {
        $instance = $this->buildTrueType("\x00\x0f", [
            'encodingTables' => [
                [
                    'platformID' => 3,
                    'encodingID' => 1,
                    'offset' => 0,
                ],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => [
                'cmap' => [
                    'offset' => 0,
                ],
            ],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->bcExpectException(FontException::class);
        $this->invokeMethod($instance, 'getCIDToGIDMap');
    }

    public function testAddCtgItemAddsGlyphToSubset(): void
    {
        $instance = $this->buildTrueType('', [
            'ctgdata' => [],
        ]);

        $this->setProperty($instance, 'subchars', [65 => true]);
        $this->invokeMethod($instance, 'addCtgItem', [65, 7]);

        $fontData = $this->getFontData($instance);
        $subGlyphs = $this->getSubglyphs($instance);

        $this->assertSame(7, $this->getCtgGlyph($fontData, 65));
        $this->assertArrayHasKey(7, $subGlyphs);
        $this->assertTrue($subGlyphs[7] ?? false);
    }

    // -------------------------------------------------------------------------
    // cmap fallback selection
    // -------------------------------------------------------------------------

    public function testSelectEncodingTableReturnsExactMatch(): void
    {
        $tables = [
            ['platformID' => 3, 'encodingID' => 1, 'offset' => 42],
            ['platformID' => 3, 'encodingID' => 10, 'offset' => 99],
        ];
        $instance = $this->buildTrueType('', [
            'encodingTables' => $tables,
            'platform_id' => 3,
            'encoding_id' => 1,
        ]);

        $result = $this->selectEncodingTable($instance);
        $this->assertNotNull($result);

        $this->assertSame(42, $result['offset']);
        $this->assertSame(3, $result['platformID']);
        $this->assertSame(1, $result['encodingID']);
    }

    public function testSelectEncodingTableFallsBackToWindowsUCS4(): void
    {
        // Requested 3/1 is absent; only 3/10 (UCS-4) is available.
        $instance = $this->buildTrueType('', [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 10, 'offset' => 7],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
        ]);

        $result = $this->selectEncodingTable($instance);
        $this->assertNotNull($result);

        $this->assertSame(3, $result['platformID']);
        $this->assertSame(10, $result['encodingID']);
        $this->assertSame(7, $result['offset']);
    }

    public function testSelectEncodingTableFallsBackToWindowsBMP(): void
    {
        // Neither 3/0 (requested) nor 3/10 present; only 3/1 available.
        $instance = $this->buildTrueType('', [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 5],
            ],
            'platform_id' => 3,
            'encoding_id' => 0,
        ]);

        $result = $this->selectEncodingTable($instance);
        $this->assertNotNull($result);

        $this->assertSame(3, $result['platformID']);
        $this->assertSame(1, $result['encodingID']);
    }

    public function testSelectEncodingTableFallsBackToPlatform0(): void
    {
        // No Windows (platform 3) subtables; should fall back to platform 0.
        $instance = $this->buildTrueType('', [
            'encodingTables' => [
                ['platformID' => 0, 'encodingID' => 3, 'offset' => 11],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
        ]);

        $result = $this->selectEncodingTable($instance);
        $this->assertNotNull($result);

        $this->assertSame(0, $result['platformID']);
        $this->assertSame(3, $result['encodingID']);
    }

    /**
     * A symbolic font ships a single Windows Symbol subtable, which is the last fallback:
     * without it the import of such a font would fail for having no usable character map.
     */
    public function testSelectEncodingTableFallsBackToWindowsSymbol(): void
    {
        $instance = $this->buildTrueType('', [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 0, 'offset' => 13],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
        ]);

        $result = $this->selectEncodingTable($instance);
        $this->assertNotNull($result);

        $this->assertSame(3, $result['platformID']);
        $this->assertSame(0, $result['encodingID']);
        $this->assertSame(13, $result['offset']);
    }

    /**
     * Windows Symbol is the last fallback, so any other usable pair is preferred to it.
     */
    public function testSelectEncodingTablePrefersMacintoshRomanToWindowsSymbol(): void
    {
        $instance = $this->buildTrueType('', [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 0, 'offset' => 13],
                ['platformID' => 1, 'encodingID' => 0, 'offset' => 21],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
        ]);

        $result = $this->selectEncodingTable($instance);
        $this->assertNotNull($result);

        $this->assertSame(1, $result['platformID']);
        $this->assertSame(0, $result['encodingID']);
    }

    public function testSelectEncodingTableReturnsNullWhenNoTableAvailable(): void
    {
        $instance = $this->buildTrueType('', [
            'encodingTables' => [
                ['platformID' => 9, 'encodingID' => 9, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
        ]);

        $result = $this->selectEncodingTable($instance);

        $this->assertNull($result);
    }

    public function testGetCIDToGIDMapThrowsWhenNoTableFound(): void
    {
        // encodingTables contains only an unrecognised platform/encoding pair.
        $instance = $this->buildTrueType('', [
            'encodingTables' => [
                ['platformID' => 9, 'encodingID' => 9, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => [
                'cmap' => ['offset' => 0],
            ],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->bcExpectException(FontException::class);
        $this->invokeMethod($instance, 'getCIDToGIDMap');
    }

    public function testGetCIDToGIDMapThrowsWhenEncodingTablesEmpty(): void
    {
        $instance = $this->buildTrueType('', [
            'encodingTables' => [],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->bcExpectException(FontException::class);
        $this->invokeMethod($instance, 'getCIDToGIDMap');
    }

    public function testGetCIDToGIDMapUsesFallbackTable(): void
    {
        // Requested 3/1 is absent; 3/10 is present with format 13, 0 groups.
        $font =
            "\x00\x0d" // format = 13
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x10" // length = 16
            . "\x00\x00\x00\x00" // language
            . "\x00\x00\x00\x00"; // numGroups = 0

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 10, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);
        // Only .notdef should be present (0 groups → nothing mapped)
        $this->assertSame([0 => 0], $this->getCtgData($fontData));
    }

    // -------------------------------------------------------------------------
    // fsType embedding policy enforcement
    // -------------------------------------------------------------------------

    public function testApplyEmbeddingPolicyThrowsOnRestrictedLicense(): void
    {
        $instance = $this->buildTrueType('', []);
        $this->bcExpectException(FontException::class);
        // 0x0002 = Restricted License only
        $this->invokeMethod($instance, 'applyEmbeddingPolicy', [0x0002]);
    }

    public function testApplyEmbeddingPolicyAllowsPreviewPrint(): void
    {
        $instance = $this->buildTrueType('', ['subset' => true]);
        // 0x0004 = Preview & Print (allowed)
        $this->invokeMethod($instance, 'applyEmbeddingPolicy', [0x0004]);
        // No exception means the policy passed; subset should be unchanged
        $fontData = $this->getFontData($instance);
        $this->assertTrue($this->getFontDataBool($fontData, 'subset'));
    }

    public function testApplyEmbeddingPolicyPermissiveBitOverridesRestricted(): void
    {
        $instance = $this->buildTrueType('', ['subset' => true]);
        // 0x0006 = 0x0002 | 0x0004: permissive override, should not throw
        $this->invokeMethod($instance, 'applyEmbeddingPolicy', [0x0006]);
        $fontData = $this->getFontData($instance);
        $this->assertTrue($this->getFontDataBool($fontData, 'subset'));
    }

    public function testApplyEmbeddingPolicyThrowsOnBitmapOnly(): void
    {
        $instance = $this->buildTrueType('', []);
        $this->bcExpectException(FontException::class);
        // 0x0200 = Bitmap Embedding Only
        $this->invokeMethod($instance, 'applyEmbeddingPolicy', [0x0200]);
    }

    public function testApplyEmbeddingPolicyThrowsOnBitmapOnlyWithEditable(): void
    {
        $instance = $this->buildTrueType('', []);
        $this->bcExpectException(FontException::class);
        // 0x0208 = Bitmap Only | Editable (the bitmap restriction still applies)
        $this->invokeMethod($instance, 'applyEmbeddingPolicy', [0x0208]);
    }

    public function testApplyEmbeddingPolicyDisablesSubsetOnNoSubsettingFlag(): void
    {
        $instance = $this->buildTrueType('', ['subset' => true]);
        // 0x0100 = No Subsetting
        $this->invokeMethod($instance, 'applyEmbeddingPolicy', [0x0100]);
        $fontData = $this->getFontData($instance);
        $this->assertFalse($this->getFontDataBool($fontData, 'subset'));
    }

    public function testApplyEmbeddingPolicyNoSubsettingWithEditableAllowed(): void
    {
        $instance = $this->buildTrueType('', ['subset' => true]);
        // 0x0108 = Editable | No Subsetting: embed allowed but no subset
        $this->invokeMethod($instance, 'applyEmbeddingPolicy', [0x0108]);
        $fontData = $this->getFontData($instance);
        $this->assertFalse($this->getFontDataBool($fontData, 'subset'));
    }

    public function testApplyEmbeddingPolicyInstallableAllowsSubset(): void
    {
        $instance = $this->buildTrueType('', ['subset' => true]);
        // 0x0000 = Installable (no restrictions)
        $this->invokeMethod($instance, 'applyEmbeddingPolicy', [0x0000]);
        $fontData = $this->getFontData($instance);
        $this->assertTrue($this->getFontDataBool($fontData, 'subset'));
    }

    // -------------------------------------------------------------------------
    // OS/2 table handling
    // -------------------------------------------------------------------------

    public function testGetOS2MetricsUsesDefaultsWhenTableAbsent(): void
    {
        // 'table' has no 'OS/2' entry at all.
        $instance = $this->buildTrueType('', [
            'table' => [],
            'urk' => 1.0,
        ]);

        $this->invokeMethod($instance, 'getOS2Metrics');
        $fontData = $this->getFontData($instance);

        $this->assertSame(0, $this->getFontDataInt($fontData, 'AvgWidth'));
        $this->assertSame(70, $this->getFontDataInt($fontData, 'StemV'));
        $this->assertSame(30, $this->getFontDataInt($fontData, 'StemH'));
    }

    public function testGetOS2MetricsThrowsWhenTableTooShort(): void
    {
        $instance = $this->buildTrueType('', [
            'table' => [
                'OS/2' => ['offset' => 0, 'length' => 5],
            ],
            'urk' => 1.0,
        ]);

        $this->bcExpectException(FontException::class);
        $this->invokeMethod($instance, 'getOS2Metrics');
    }

    public function testGetOS2MetricsParsesValidTable(): void
    {
        // Minimal valid OS/2 blob: 10 bytes.
        // version(2) + xAvgCharWidth(2) + usWeightClass(2) + usWidthClass(2) + fsType(2)
        $font =
            "\x00\x04" // version = 4
            . "\x04\x00" // xAvgCharWidth = 1024 raw units
            . "\x01\x90" // usWeightClass = 400
            . "\x00\x05" // usWidthClass  = 5 (unused)
            . "\x00\x08"; // fsType = 0x0008 (Editable, allowed)

        $instance = $this->buildTrueType($font, [
            'table' => [
                'OS/2' => ['offset' => 0, 'length' => 10],
            ],
            'urk' => 1.0,
            'subset' => true,
        ]);

        $this->invokeMethod($instance, 'getOS2Metrics');
        $fontData = $this->getFontData($instance);

        // xAvgCharWidth 0x0400 = 1024 raw, * urk 1.0 = 1024
        $this->assertSame(1024, $this->getFontDataInt($fontData, 'AvgWidth'));
        // usWeightClass 0x0190 = 400; StemV = round(70*400/400) = 70
        $this->assertSame(70, $this->getFontDataInt($fontData, 'StemV'));
        // StemH = round(30*400/400) = 30
        $this->assertSame(30, $this->getFontDataInt($fontData, 'StemH'));
        // Editable flag: subset must remain true (no restriction)
        $this->assertTrue($this->getFontDataBool($fontData, 'subset'));
    }

    public function testGetOS2MetricsNoSubsettingFlagDisablesSubset(): void
    {
        // fsType = 0x0100 (No Subsetting)
        $font =
            "\x00\x04" // version
            . "\x02\x00" // xAvgCharWidth
            . "\x01\x90" // usWeightClass = 400
            . "\x00\x05" // usWidthClass
            . "\x01\x00"; // fsType = 0x0100

        $instance = $this->buildTrueType($font, [
            'table' => ['OS/2' => ['offset' => 0, 'length' => 10]],
            'urk' => 1.0,
            'subset' => true,
        ]);

        $this->invokeMethod($instance, 'getOS2Metrics');
        $fontData = $this->getFontData($instance);

        $this->assertFalse($this->getFontDataBool($fontData, 'subset'));
    }

    /**
     * @param array<string, mixed> $fdt
     *
     * @return array<string, mixed>
     */
    protected function getFontDefaults(array $fdt = []): array
    {
        $defaults = [
            'Ascender' => 0,
            'Ascent' => 0,
            'AvgWidth' => 0.0,
            'CapHeight' => 0,
            'CharacterSet' => '',
            'Descender' => 0,
            'Descent' => 0,
            'EncodingScheme' => '',
            'FamilyName' => '',
            'Flags' => 0,
            'FontBBox' => [],
            'FontName' => '',
            'FullName' => '',
            'IsFixedPitch' => false,
            'ItalicAngle' => 0,
            'Leading' => 0,
            'MaxWidth' => 0,
            'MissingWidth' => 0,
            'StdHW' => 0,
            'StdVW' => 0,
            'StemH' => 0,
            'StemV' => 0,
            'UnderlinePosition' => 0,
            'UnderlineThickness' => 0,
            'Version' => '',
            'Weight' => '',
            'XHeight' => 0,
            'bbox' => '',
            'cbbox' => [],
            'cidinfo' => ['Ordering' => '', 'Registry' => '', 'Supplement' => 0, 'uni2cid' => []],
            'compress' => false,
            'ctg' => '',
            'ctgdata' => [],
            'cw' => [],
            'cwu' => [],
            'datafile' => '',
            'desc' => [
                'Ascent' => 0,
                'AvgWidth' => 0,
                'CapHeight' => 0,
                'Descent' => 0,
                'Flags' => 0,
                'FontBBox' => '',
                'ItalicAngle' => 0,
                'Leading' => 0,
                'MaxWidth' => 0,
                'MissingWidth' => 0,
                'StemH' => 0,
                'StemV' => 0,
                'XHeight' => 0,
            ],
            'diff' => '',
            'diff_n' => 0,
            'dir' => '',
            'dw' => 0,
            'enc' => '',
            'enc_map' => [],
            'encodingTables' => [],
            'encoding_id' => 0,
            'encrypted' => '',
            'fakestyle' => false,
            'family' => '',
            'file' => '',
            'file_n' => 0,
            'file_name' => '',
            'i' => 0,
            'ifile' => '',
            'indexToLoc' => [],
            'input_file' => '',
            'isUnicode' => false,
            'italicAngle' => 0,
            'key' => '',
            'lenIV' => 0,
            'length1' => 0,
            'length2' => 0,
            'linked' => false,
            'mode' => [
                'bold' => false,
                'italic' => false,
                'linethrough' => false,
                'overline' => false,
                'underline' => false,
            ],
            'n' => 0,
            'name' => '',
            'numGlyphs' => 0,
            'numHMetrics' => 0,
            'originalsize' => 0,
            'pdfa' => false,
            'platform_id' => 0,
            'settype' => '',
            'short_offset' => false,
            'size1' => 0,
            'size2' => 0,
            'style' => '',
            'subset' => false,
            'subsetchars' => [],
            'table' => [],
            'tot_num_glyphs' => 0,
            'type' => '',
            'underlinePosition' => 0,
            'underlineThickness' => 0,
            'unicode' => false,
            'unitsPerEm' => 0,
            'up' => 0,
            'urk' => 0.0,
            'ut' => 0,
            'weight' => '',
        ];

        return \array_replace_recursive($defaults, $fdt);
    }

    /**
     * @param array<string, mixed> $fdt
     */
    protected function buildTrueType(string $font, array $fdt): TrueType
    {
        $class = new \ReflectionClass(TrueType::class);
        $instance = $class->newInstanceWithoutConstructor();
        try {
            $byte = new Byte($font);
        } catch (\RangeException $exception) {
            $this->fail($exception->getMessage());
        }

        $this->setProperty($instance, 'font', $font);
        $this->setProperty($instance, 'fdt', $this->getFontDefaults($this->withTableLengths($fdt, \strlen($font))));
        $this->setProperty($instance, 'fbyte', $byte);
        $this->setProperty($instance, 'offset', 0);

        return $instance;
    }

    /**
     * Complete the table records of a fixture with the length getTables() always records.
     *
     * The length of the synthetic program stands in for the missing value.
     *
     * @param array<string, mixed> $fdt    Font data of the fixture.
     * @param int                  $length Length of the synthetic font program.
     *
     * @return array<string, mixed>
     */
    private function withTableLengths(array $fdt, int $length): array
    {
        if (!isset($fdt['table']) || !\is_array($fdt['table'])) {
            return $fdt;
        }

        $tables = [];
        /** @var mixed $record */
        foreach ($fdt['table'] as $tag => $record) {
            if (\is_array($record) && !isset($record['length'])) {
                $record['length'] = $length;
            }

            $tables[$tag] = $record;
        }

        $fdt['table'] = $tables;
        return $fdt;
    }

    /**
     * @param array<int, mixed> $args
     */
    protected function invokeMethod(TrueType $instance, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod(TrueType::class, $method);

        return $reflection->invokeArgs($instance, $args);
    }

    /** @return array<string, mixed> */
    protected function getFontData(TrueType $instance): array
    {
        return $this->expectFontData($this->getProperty($instance, 'fdt'));
    }

    /** @param array<string, mixed> $fontData */
    protected function getCtgGlyph(array $fontData, int $char): ?int
    {
        $ctgData = $this->getCtgData($fontData);
        $glyph = $ctgData[$char] ?? null;

        if ($glyph !== null) {
            $this->assertIsInt($glyph);
        }

        return $glyph;
    }

    /**
     * @param array<string, mixed> $fontData
     *
     * @return array<int, int>
     */
    protected function getCtgData(array $fontData): array
    {
        if (!isset($fontData['ctgdata']) || !\is_array($fontData['ctgdata'])) {
            $this->fail('Expected ctgdata map.');
        }

        /** @var array<int, int> $ctgData */
        $ctgData = $fontData['ctgdata'];
        return $ctgData;
    }

    /** @param array<string, mixed> $fontData */
    protected function getFontDataString(array $fontData, string $key): string
    {
        if (!isset($fontData[$key]) || !\is_string($fontData[$key])) {
            $this->fail('Expected string font field: ' . $key);
        }

        return $fontData[$key];
    }

    /** @param array<string, mixed> $fontData */
    protected function getFontDataBool(array $fontData, string $key): bool
    {
        if (!isset($fontData[$key]) || !\is_bool($fontData[$key])) {
            $this->fail('Expected bool font field: ' . $key);
        }

        return $fontData[$key];
    }

    /** @param array<string, mixed> $fontData */
    protected function getFontDataInt(array $fontData, string $key): int
    {
        if (!isset($fontData[$key]) || !\is_int($fontData[$key])) {
            $this->fail('Expected int font field: ' . $key);
        }

        return $fontData[$key];
    }

    /**
     * @return array<int, bool>
     */
    protected function getSubglyphs(TrueType $instance): array
    {
        return $this->expectSubglyphs($this->getProperty($instance, 'subglyphs'));
    }

    /**
     * @return array{platformID: int, encodingID: int, offset: int}|null
     */
    protected function selectEncodingTable(TrueType $instance): ?array
    {
        return $this->expectEncodingTable($this->invokeMethod($instance, 'selectEncodingTable'));
    }

    protected function convertStringEncoding(TrueType $instance, string $str, int $platformId, int $encodingId): string
    {
        return $this->expectString(
            $this->invokeMethod($instance, 'convertStringEncoding', [$str, $platformId, $encodingId]),
            'Expected converted string.',
        );
    }

    protected function getProperty(TrueType $instance, string $name): mixed
    {
        $property = new \ReflectionProperty(TrueType::class, $name);

        return $property->getValue($instance);
    }

    protected function setProperty(TrueType $instance, string $name, mixed $value): void
    {
        $property = new \ReflectionProperty(TrueType::class, $name);
        $property->setValue($instance, $value);
    }

    /** @return array<string, mixed> */
    protected function expectFontData(mixed $value): array
    {
        if (!\is_array($value)) {
            $this->fail('Expected font data array.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /** @return array<int, bool> */
    protected function expectSubglyphs(mixed $value): array
    {
        if (!\is_array($value)) {
            $this->fail('Expected subglyph map.');
        }

        /** @var array<int, bool> $value */
        return $value;
    }

    /** @return array{platformID: int, encodingID: int, offset: int}|null */
    protected function expectEncodingTable(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!\is_array($value)) {
            $this->fail('Expected encoding table array.');
        }

        if (!isset($value['platformID']) || !\is_int($value['platformID'])) {
            $this->fail('Expected platformID.');
        }

        if (!isset($value['encodingID']) || !\is_int($value['encodingID'])) {
            $this->fail('Expected encodingID.');
        }

        if (!isset($value['offset']) || !\is_int($value['offset'])) {
            $this->fail('Expected offset.');
        }

        return [
            'platformID' => $value['platformID'],
            'encodingID' => $value['encodingID'],
            'offset' => $value['offset'],
        ];
    }

    protected function expectString(mixed $value, string $message): string
    {
        if (!\is_string($value)) {
            $this->fail($message);
        }

        return $value;
    }

    // -------------------------------------------------------------------------
    // cmap format 0: byte encoding table
    // -------------------------------------------------------------------------

    public function testProcessFormat0MapsAllGlyphs(): void
    {
        // Format 0: 256-byte direct lookup. After getCIDToGIDMap reads the 2-byte
        // format field, processFormat0 skips 4 bytes (length + language) then reads
        // 256 single-byte glyph IDs.
        $glyphs = str_repeat("\x00", 256);
        $glyphs[65] = "\x63"; // chr 65 → glyph 99
        $glyphs[90] = "\x0A"; // chr 90 → glyph 10

        $font =
            "\x00\x00" // format = 0
            . "\x01\x06" // length (unused)
            . "\x00\x00" // language (unused)
            . $glyphs;

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(99, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(10, $this->getCtgGlyph($fontData, 90));
        // All 256 slots populated and type was TrueTypeUnicode → converted to TrueType
        $this->assertSame('TrueType', $this->getFontDataString($fontData, 'type'));
    }

    // -------------------------------------------------------------------------
    // cmap format 2: high-byte mapping through table
    // -------------------------------------------------------------------------

    public function testProcessFormat2MapsCharsViaSingleByteSubheaders(): void
    {
        // All 256 subHeaderKeys = 0  → single-byte codes, one subHeader at index 0.
        // subHeaders[0]: firstCode=0, entryCount=1, idDelta=0, idRangeOffset=2
        //   Adjusted idRangeOffset = 2 − (2 + (1−0−1)×8) = 0  →  /2 = 0
        // Only code 0 is inside the declared range: it maps to glyph 99, and every other
        // byte resolves to notdef.
        $subHeaderKeys = str_repeat("\x00\x00", 256); // 512 bytes
        $subHeader = "\x00\x00\x00\x01\x00\x00\x00\x02";
        $glyphIdArray = "\x00\x63"; // glyph 99

        $font =
            "\x00\x02" // format = 2
            . "\x02\x18" // length (unused)
            . "\x00\x00" // language (unused)
            . $subHeaderKeys
            . $subHeader
            . $glyphIdArray;

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(99, $this->getCtgGlyph($fontData, 0));
        $this->assertSame(0, $this->getCtgGlyph($fontData, 65));
        // 256 entries with TrueTypeUnicode → converted to TrueType
        $this->assertSame('TrueType', $this->getFontDataString($fontData, 'type'));
    }

    public function testProcessFormat2MapsEverySingleByteCodeThroughSubHeaderZero(): void
    {
        // subHeaders[0]: firstCode=65, entryCount=2, idDelta=10, idRangeOffset=2
        //   Adjusted idRangeOffset = 2 − (2 + (1−0−1)×8) = 0  →  /2 = 0
        // glyphIndexArray = [7, 0]: code 65 → 7 + idDelta, code 66 → 0 (missingGlyph,
        // which is kept as notdef instead of being shifted by idDelta).
        $font = $this->buildFormat2([], [[65, 2, 10, 2]], [7, 0]);

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(17, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(0, $this->getCtgGlyph($fontData, 66));
        // outside the range declared by the sub-header
        $this->assertSame(0, $this->getCtgGlyph($fontData, 64));
    }

    // -------------------------------------------------------------------------
    // cmap format 6: trimmed table mapping
    // -------------------------------------------------------------------------

    public function testProcessFormat6MapsCharRange(): void
    {
        // firstCode=65, entryCount=3, glyphs=[10,11,12]
        $font =
            "\x00\x06" // format = 6
            . "\x00\x0F" // length (unused)
            . "\x00\x00" // language (unused)
            . "\x00\x41" // firstCode = 65
            . "\x00\x03" // entryCount = 3
            . "\x00\x0A" // glyph for chr 65 = 10
            . "\x00\x0B" // glyph for chr 66 = 11
            . "\x00\x0C"; // glyph for chr 67 = 12

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(10, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(11, $this->getCtgGlyph($fontData, 66));
        $this->assertSame(12, $this->getCtgGlyph($fontData, 67));
        // Only 4 ctgdata entries → not 256 → type stays TrueTypeUnicode
        $this->assertSame('TrueTypeUnicode', $this->getFontDataString($fontData, 'type'));
    }

    // -------------------------------------------------------------------------
    // cmap format 8: mixed 16-bit and 32-bit coverage
    // -------------------------------------------------------------------------

    public function testProcessFormat8WithNoGroupsAddsOnlyNotdef(): void
    {
        // numGroups = 0 → no character mappings; only the .notdef fallback is present.
        $is32 = str_repeat("\x00", 8192);
        $font =
            "\x00\x08" // format = 8
            . "\x00\x00" // reserved (uint16)
            . "\x00\x00\x20\x14" // length (uint32, unused)
            . "\x00\x00\x00\x00" // language (uint32, unused)
            . $is32 // is32[8192]
            . "\x00\x00\x00\x00"; // numGroups = 0

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame([0 => 0], $this->getCtgData($fontData));
    }

    public function testProcessFormat8MapsSingleByteChar(): void
    {
        // numGroups=1: chars 65..65 → glyph 5. is32[8]=0 → single-byte char.
        // The character maps to startGlyphID, and subglyphs holds glyph 5 when
        // subchars[65] is set.
        $is32 = str_repeat("\x00", 8192);
        $font =
            "\x00\x08" // format = 8
            . "\x00\x00" // reserved
            . "\x00\x00\x20\x26" // length (unused)
            . "\x00\x00\x00\x00" // language (unused)
            . $is32 // is32[8192] all zeros
            . "\x00\x00\x00\x01" // numGroups = 1
            . "\x00\x00\x00\x41" // startCharCode = 65
            . "\x00\x00\x00\x41" // endCharCode   = 65
            . "\x00\x00\x00\x05"; // startGlyphID  = 5

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);
        $this->setProperty($instance, 'subchars', [65 => true]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);
        $subGlyphs = $this->getSubglyphs($instance);

        // ctgdata[65] maps to the real glyph id 5 (startGlyphID)
        $this->assertSame(5, $this->getCtgGlyph($fontData, 65));
        // addCtgItem also recorded glyph 5 in subglyphs
        $this->assertArrayHasKey(5, $subGlyphs);
    }

    // -------------------------------------------------------------------------
    // cmap format 10: trimmed array
    // -------------------------------------------------------------------------

    public function testProcessFormat10MapsCharRange(): void
    {
        // startCharCode=65, numChars=3, glyphs=[10,11,12]
        $font =
            "\x00\x0A" // format = 10
            . "\x00\x00" // reserved (uint16)
            . "\x00\x00\x00\x1C" // length (uint32, unused)
            . "\x00\x00\x00\x00" // language (uint32, unused)
            . "\x00\x00\x00\x41" // startCharCode = 65 (uint32)
            . "\x00\x00\x00\x03" // numChars = 3 (uint32)
            . "\x00\x0A" // glyph for chr 65 = 10
            . "\x00\x0B" // glyph for chr 66 = 11
            . "\x00\x0C"; // glyph for chr 67 = 12

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(10, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(11, $this->getCtgGlyph($fontData, 66));
        $this->assertSame(12, $this->getCtgGlyph($fontData, 67));
        $this->assertSame(0, $this->getCtgGlyph($fontData, 0));
    }

    // -------------------------------------------------------------------------
    // cmap format 12: segmented coverage
    // -------------------------------------------------------------------------

    public function testProcessFormat12MapsSequentialGlyphs(): void
    {
        // 1 group: startCharCode=65, endCharCode=67, startGlyphID=100
        // → ctgdata[65]=100, ctgdata[66]=101, ctgdata[67]=102
        $font =
            "\x00\x0C" // format = 12
            . "\x00\x00" // reserved (uint16)
            . "\x00\x00\x00\x22" // length (uint32, unused)
            . "\x00\x00\x00\x00" // language (uint32, unused)
            . "\x00\x00\x00\x01" // nGroups = 1
            . "\x00\x00\x00\x41" // startCharCode = 65
            . "\x00\x00\x00\x43" // endCharCode   = 67
            . "\x00\x00\x00\x64"; // startGlyphID  = 100

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(100, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(101, $this->getCtgGlyph($fontData, 66));
        $this->assertSame(102, $this->getCtgGlyph($fontData, 67));
        $this->assertSame(0, $this->getCtgGlyph($fontData, 0));
    }

    public function testProcessFormat12WithZeroGroupsAddsOnlyNotdef(): void
    {
        $font =
            "\x00\x0C" // format = 12
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x16" // length (unused)
            . "\x00\x00\x00\x00" // language (unused)
            . "\x00\x00\x00\x00"; // nGroups = 0

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame([0 => 0], $this->getCtgData($fontData));
    }

    public function testProcessFormat12ClampsRangeToUnicodeMax(): void
    {
        // A group ending at 0xFFFFFFFF must be clamped to U+10FFFF instead of iterating
        // ~4 billion times (DoS guard). Only the two in-range code points are mapped.
        $font =
            "\x00\x0C" // format = 12
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x1C" // length (unused)
            . "\x00\x00\x00\x00" // language (unused)
            . "\x00\x00\x00\x01" // nGroups = 1
            . "\x00\x10\xFF\xFE" // startCharCode = 0x10FFFE
            . "\xFF\xFF\xFF\xFF" // endCharCode   = 0xFFFFFFFF (clamped to 0x10FFFF)
            . "\x00\x00\x00\x07"; // startGlyphID  = 7

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(7, $this->getCtgGlyph($fontData, 0x10FFFE));
        $this->assertSame(8, $this->getCtgGlyph($fontData, 0x10FFFF));
        // nothing beyond the Unicode maximum was mapped
        $this->assertNull($this->getCtgGlyph($fontData, 0x110000));
    }

    public function testProcessFormat12SkipsGroupAboveUnicodeMax(): void
    {
        // A group that starts beyond U+10FFFF is skipped entirely.
        $font =
            "\x00\x0C" // format = 12
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x1C" // length (unused)
            . "\x00\x00\x00\x00" // language (unused)
            . "\x00\x00\x00\x01" // nGroups = 1
            . "\x00\x11\x00\x00" // startCharCode = 0x110000 (> U+10FFFF)
            . "\x00\x11\x00\x05" // endCharCode   = 0x110005
            . "\x00\x00\x00\x07"; // startGlyphID  = 7

        $instance = $this->buildTrueType($font, [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0]],
            'type' => 'TrueTypeUnicode',
        ]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame([0 => 0], $this->getCtgData($fontData));
    }

    // -------------------------------------------------------------------------
    // checkRequiredTables
    // -------------------------------------------------------------------------

    public function testCheckRequiredTablesThrowsWhenTableMissing(): void
    {
        // every mandatory table is present except 'glyf'
        $tables = [];
        foreach (['head', 'loca', 'cmap', 'name', 'post', 'hhea', 'maxp', 'hmtx'] as $tag) {
            $tables[$tag] = ['checkSum' => 0, 'data' => '', 'length' => 0, 'offset' => 0];
        }

        $instance = $this->buildTrueType('', ['table' => $tables]);

        $this->expectException(FontException::class);
        $this->invokeMethod($instance, 'checkRequiredTables');
    }

    // -------------------------------------------------------------------------
    // convertStringEncoding
    // -------------------------------------------------------------------------

    public function testConvertStringEncodingForUnicodePlatformUtf16be(): void
    {
        $instance = $this->buildTrueType('', []);
        // platformId=0 (Unicode) → UTF-16BE. "\x00\x41" = 'A'
        $result = $this->convertStringEncoding($instance, "\x00\x41", 0, 0);
        $this->assertSame('A', $result);
    }

    public function testConvertStringEncodingForWindowsPlatformDefaultUtf16be(): void
    {
        $instance = $this->buildTrueType('', []);
        // platformId=3, encodingId=0 → default UTF-16BE. "\x00\x42" = 'B'
        $result = $this->convertStringEncoding($instance, "\x00\x42", 3, 0);
        $this->assertSame('B', $result);
    }

    public function testConvertStringEncodingForWindowsPlatformEncodingId1Utf16be(): void
    {
        $instance = $this->buildTrueType('', []);
        // platformId=3, encodingId=1 → default UTF-16BE. "\x00\x43" = 'C'
        $result = $this->convertStringEncoding($instance, "\x00\x43", 3, 1);
        $this->assertSame('C', $result);
    }

    public function testConvertStringEncodingForMacintoshPlatformAsciiChar(): void
    {
        $instance = $this->buildTrueType('', []);
        // platformId=1 (Macintosh/MacRoman). ASCII 0x41 = 'A' in both MacRoman and UTF-8.
        $result = $this->convertStringEncoding($instance, "\x41", 1, 0);
        $this->assertSame('A', $result);
    }

    public function testConvertStringEncodingForWindowsPlatformCp936(): void
    {
        $instance = $this->buildTrueType('', []);
        // platformId=3, encodingId=3 → CP936.
        // CP936/GBK 0x41 (single-byte ASCII-compatible) = 'A'
        $result = $this->convertStringEncoding($instance, "\x41", 3, 3);
        $this->assertSame('A', $result);
    }

    public function testConvertStringEncodingForWindowsPlatformShiftJis(): void
    {
        $instance = $this->buildTrueType('', []);
        // platformId=3, encodingId=2 → Shift-JIS (CP932): 0x82A0 is 'あ'.
        // Decoded as UTF-16BE it would yield an unrelated codepoint instead.
        $result = $this->convertStringEncoding($instance, "\x82\xA0", 3, 2);
        $this->assertSame("\u{3042}", $result);
    }

    public function testConvertStringEncodingForIsoPlatformIsSingleByte(): void
    {
        $instance = $this->buildTrueType('', []);
        // the deprecated platformId=2 (ISO) stores single-byte strings: read as UTF-16BE
        // the two bytes would collapse into one unrelated character
        $result = $this->convertStringEncoding($instance, "\x41\x42", 2, 0);
        $this->assertSame('AB', $result);
    }

    public function testConvertStringEncodingFallsBackToWindows1252WithoutIconv(): void
    {
        // ext-iconv is only suggested by composer.json, so a deployment without it decodes
        // the Macintosh name records with the closest mbstring substitute instead.
        $class = new \ReflectionClass(TrueTypeNoIconvHarness::class);
        $instance = $class->newInstanceWithoutConstructor();

        // 0xA9 is the copyright sign in Windows-1252
        $this->assertSame("\u{00A9}", $instance->runConvertStringEncoding("\xA9", 1, 0));
    }

    // -------------------------------------------------------------------------
    // head table: unitsPerEm validation
    // -------------------------------------------------------------------------

    /**
     * Build the slice of the head table that getBbox consumes, starting at unitsPerEm.
     */
    private function headBboxBytes(int $unitsPerEm): string
    {
        return (
            pack('n', $unitsPerEm) // unitsPerEm
            . str_repeat("\x00", 16) // created + modified (LONGDATETIME x2)
            . "\x00\x00" // xMin
            . "\xFF\x38" // yMin = -200
            . "\x03\xE8" // xMax = 1000
            . "\x03\x20" // yMax = 800
            . "\x00\x00" // macStyle
        );
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function invalidUnitsPerEmProvider(): array
    {
        return [
            'zero would divide by zero' => [0],
            'below the spec minimum' => [15],
            'above the spec maximum' => [16385],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidUnitsPerEmProvider')]
    public function testGetBboxRejectsUnitsPerEmOutsideTheSpecRange(int $unitsPerEm): void
    {
        // the value feeds 1000/unitsPerEm, where 0 would raise a DivisionByZeroError
        $instance = $this->buildTrueType($this->headBboxBytes($unitsPerEm), []);
        $this->assertThrowsMessage(
            FontException::class,
            'unitsPerEm',
            fn() => $this->invokeMethod($instance, 'getBbox'),
        );
    }

    /**
     * @return array<string, array{0: int, 1: float}>
     */
    public static function validUnitsPerEmProvider(): array
    {
        return [
            'spec minimum' => [16, 62.5],
            'typical' => [1000, 1.0],
            'power of two' => [2048, 1000 / 2048],
            'spec maximum' => [16384, 1000 / 16384],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validUnitsPerEmProvider')]
    public function testGetBboxAcceptsUnitsPerEmInsideTheSpecRange(int $unitsPerEm, float $urk): void
    {
        $instance = $this->buildTrueType($this->headBboxBytes($unitsPerEm), []);
        $this->invokeMethod($instance, 'getBbox');
        $fontData = $this->getFontData($instance);

        $this->assertSame($unitsPerEm, $this->getFontDataInt($fontData, 'unitsPerEm'));
        $this->assertEqualsWithDelta($urk, $fontData['urk'] ?? 0.0, 1e-9);
    }

    public function testGetBboxSetsItalicFlagFromMacStyle(): void
    {
        // macStyle bit 1 (value 2) = italic
        $font = substr($this->headBboxBytes(1000), 0, -2) . "\x00\x02";
        $instance = $this->buildTrueType($font, []);
        $this->invokeMethod($instance, 'getBbox');
        $fontData = $this->getFontData($instance);

        $this->assertSame(64, $this->getFontDataInt($fontData, 'Flags') & 64);
        $this->assertSame('0 -200 1000 800', $this->getFontDataString($fontData, 'bbox'));
    }

    // -------------------------------------------------------------------------
    // loca table: offsets bounded by the glyf table
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{0: bool, 1: string}>
     */
    public static function locaFormatProvider(): array
    {
        // head.indexToLocFormat is read at head+50: 0 = short (Offset16, halved), 1 = long
        return [
            // short offsets are stored halved, so 0xFFFF stands for 131070
            'short offsets' => [true, "\x00\x00\xFF\xFF\x00\x08"],
            'long offsets' => [false, "\x00\x00\x00\x00\x00\x00\xFF\xFF\x00\x00\x00\x10"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('locaFormatProvider')]
    public function testGetIndexToLocClampsOffsetsToTheGlyfTable(bool $shortOffsets, string $loca): void
    {
        // head: only the indexToLocFormat field at +50 is read here
        $head = \str_repeat("\x00", 50) . ($shortOffsets ? "\x00\x00" : "\x00\x01");
        $instance = $this->buildTrueType($head . $loca, [
            'numGlyphs' => 2,
            'table' => [
                'glyf' => ['length' => 32, 'offset' => 0],
                'head' => ['length' => 54, 'offset' => 0],
                'loca' => ['length' => \strlen($loca), 'offset' => 52],
            ],
        ]);

        $this->invokeMethod($instance, 'getIndexToLoc');
        $fontData = $this->getFontData($instance);

        /** @var array<int, int> $indexToLoc */
        $indexToLoc = $fontData['indexToLoc'] ?? [];
        $this->assertSame(0, $indexToLoc[0] ?? null);
        // the corrupt entry is clamped to the end of glyf, so the glyph is empty instead
        // of pointing into the table that follows it
        $this->assertSame(32, $indexToLoc[1] ?? null);
        $this->assertSame($shortOffsets, $this->getFontDataBool($fontData, 'short_offset'));
    }

    /**
     * The short format stores the offsets halved, so it can only address even ones: a glyf
     * table of odd declared length is clamped to the even offset below it.
     */
    public function testGetIndexToLocClampsShortOffsetsToAnEvenLength(): void
    {
        $head = \str_repeat("\x00", 50) . "\x00\x00"; // short offsets
        $loca = "\x00\x00\xFF\xFF\x00\x08";
        $instance = $this->buildTrueType($head . $loca, [
            'numGlyphs' => 2,
            'table' => [
                'glyf' => ['length' => 33, 'offset' => 0],
                'head' => ['length' => 54, 'offset' => 0],
                'loca' => ['length' => \strlen($loca), 'offset' => 52],
            ],
        ]);

        $this->invokeMethod($instance, 'getIndexToLoc');
        $fontData = $this->getFontData($instance);

        /** @var array<int, int> $indexToLoc */
        $indexToLoc = $fontData['indexToLoc'] ?? [];
        $this->assertSame(0, $indexToLoc[0] ?? null);
        $this->assertSame(32, $indexToLoc[1] ?? null);
    }

    // -------------------------------------------------------------------------
    // hmtx table: numberOfHMetrics validation
    // -------------------------------------------------------------------------

    public function testGetWidthsRejectsZeroNumHMetrics(): void
    {
        // with numHMetrics = 0 the metric loop does not run, leaving no advance to repeat
        $instance = $this->buildTrueType(str_repeat("\x00", 64), [
            'numGlyphs' => 4,
            'numHMetrics' => 0,
            'table' => ['hmtx' => ['length' => 64, 'offset' => 0]],
            'urk' => 1.0,
        ]);
        $this->setProperty($instance, 'withCbbox', false);

        $this->assertThrowsMessage(
            FontException::class,
            'numberOfHMetrics',
            fn() => $this->invokeMethod($instance, 'getWidths'),
        );
    }

    public function testGetWidthsClampsNumHMetricsToTheHmtxTableLength(): void
    {
        // hhea declares 4 metrics but hmtx only holds 2: the extra records would be read
        // from whatever table follows and turned into advance widths.
        $font =
            "\x01\xF4\x00\x00" // glyph 0: advance 500
            . "\x02\x58\x00\x00" // glyph 1: advance 600
            . "\x27\x10\x00\x00" // not part of hmtx: advance 10000 if read
            . "\x27\x10\x00\x00";
        $instance = $this->buildTrueType($font, [
            'ctgdata' => [65 => 0, 66 => 1, 67 => 2, 68 => 3],
            'numGlyphs' => 4,
            'numHMetrics' => 4,
            'table' => ['hmtx' => ['length' => 8, 'offset' => 0]],
            'urk' => 1.0,
        ]);
        $this->setProperty($instance, 'withCbbox', false);

        $this->invokeMethod($instance, 'getWidths');
        $fontData = $this->getFontData($instance);

        /** @var array<int, int> $cwd */
        $cwd = $fontData['cw'] ?? [];
        $this->assertSame(2, $this->getFontDataInt($fontData, 'numHMetrics'));
        // the glyphs past the table repeat the last real advance instead of the neighbours
        $this->assertSame(600, $cwd[67] ?? null);
        $this->assertSame(600, $cwd[68] ?? null);
    }

    public function testGetWidthsRejectsAnHmtxTableTooShortForOneMetric(): void
    {
        $instance = $this->buildTrueType(str_repeat("\x00", 64), [
            'numGlyphs' => 1,
            'numHMetrics' => 1,
            'table' => ['hmtx' => ['length' => 2, 'offset' => 0]],
            'urk' => 1.0,
        ]);
        $this->setProperty($instance, 'withCbbox', false);

        $this->assertThrowsMessage(
            FontException::class,
            'hmtx table is too short',
            fn() => $this->invokeMethod($instance, 'getWidths'),
        );
    }

    public function testGetWidthsSkipsTheBboxOfAGlyphOutsideTheGlyfTable(): void
    {
        // loca points at the very end of glyf, so the 10-byte glyph header does not fit:
        // the bounding box would be read from the table that follows.
        $font = "\x01\xF4\x00\x00" . str_repeat("\x7F", 64);
        $instance = $this->buildTrueType($font, [
            'ctgdata' => [65 => 1],
            'indexToLoc' => [1 => 8],
            'numGlyphs' => 2,
            'numHMetrics' => 1,
            'table' => [
                'glyf' => ['length' => 12, 'offset' => 4],
                'hmtx' => ['length' => 4, 'offset' => 0],
            ],
            'urk' => 1.0,
        ]);
        $this->setProperty($instance, 'withCbbox', true);

        $this->invokeMethod($instance, 'getWidths');
        $fontData = $this->getFontData($instance);

        $this->assertSame([], $fontData['cbbox'] ?? null);
    }

    public function testGetWidthsPadsMissingMetricsWithTheLastAdvance(): void
    {
        // hmtx: 3 longHorMetric records (advanceWidth + lsb, 2 bytes each)
        $font =
            "\x01\xF4\x00\x00" // glyph 0: advance 500
            . "\x02\x58\x00\x00" // glyph 1: advance 600
            . "\x02\xBC\x00\x00"; // glyph 2: advance 700
        $instance = $this->buildTrueType($font, [
            // codepoint 64 is mapped to .notdef by this cmap, and carries no width
            'ctgdata' => [64 => 0, 65 => 1, 66 => 2, 67 => 3],
            'numGlyphs' => 4,
            'numHMetrics' => 3,
            'table' => ['hmtx' => ['length' => 12, 'offset' => 0]],
            'urk' => 1.0,
        ]);
        $this->setProperty($instance, 'withCbbox', false);

        $this->invokeMethod($instance, 'getWidths');
        $fontData = $this->getFontData($instance);

        /** @var array<int, int> $cwd */
        $cwd = $fontData['cw'] ?? [];
        $this->assertSame(600, $cwd[65] ?? null);
        $this->assertSame(700, $cwd[66] ?? null);
        // glyph 3 has no hmtx record: it repeats the last advance
        $this->assertSame(700, $cwd[67] ?? null);
        $this->assertSame(500, $this->getFontDataInt($fontData, 'MissingWidth'));
    }

    // -------------------------------------------------------------------------
    // glyf table: heights of glyphs without an outline
    // -------------------------------------------------------------------------

    public function testGetHeightsKeepsDerivedValuesWhenGlyphsHaveNoOutline(): void
    {
        // getIndexToLoc deliberately unsets the entry of a glyph without an outline, so
        // indexToLoc[gid] can be missing even though ctgdata[gid] is a non-zero glyph id.
        $instance = $this->buildTrueType(str_repeat("\x00", 64), [
            'Ascent' => 800,
            'Descent' => -200,
            'ctgdata' => [72 => 7, 120 => 5],
            'indexToLoc' => [], // both outlines were dropped
            'table' => ['glyf' => ['length' => 64, 'offset' => 0]],
            'urk' => 1.0,
        ]);

        $this->invokeMethod($instance, 'getHeights');
        $fontData = $this->getFontData($instance);

        // XHeight falls back to Ascent + Descent, CapHeight to Ascent
        $this->assertSame(600, $this->getFontDataInt($fontData, 'XHeight'));
        $this->assertSame(800, $this->getFontDataInt($fontData, 'CapHeight'));
    }

    public function testGetHeightsReadsTheGlyphBboxWhenTheOutlineIsPresent(): void
    {
        // glyf record layout read here: +4 = yMin, +8 = yMax (2 bytes each)
        $glyf =
            "\x00\x00\x00\x00" // numberOfContours + xMin
            . "\x00\x00" // yMin = 0
            . "\x00\x00" // xMax
            . "\x01\xF4" // yMax = 500
            . "\x00\x00";
        $instance = $this->buildTrueType($glyf . str_repeat("\x00", 32), [
            'Ascent' => 800,
            'Descent' => -200,
            'ctgdata' => [120 => 1],
            'indexToLoc' => [1 => 0],
            'table' => ['glyf' => ['length' => 42, 'offset' => 0]],
            'urk' => 1.0,
        ]);

        $this->invokeMethod($instance, 'getHeights');
        $fontData = $this->getFontData($instance);

        $this->assertSame(500, $this->getFontDataInt($fontData, 'XHeight'));
    }

    /**
     * ISO 32000-1 Table 122 defines /XHeight and /CapHeight as heights above the baseline,
     * so both are the yMax of the glyph alone.
     */
    public function testGetHeightsMeasuresFromTheBaselineNotTheGlyphExtent(): void
    {
        // an 'x' with a 10 unit overshoot below the baseline: yMin = -10, yMax = 500
        $glyf =
            "\x00\x00\x00\x00" // numberOfContours + xMin
            . "\xFF\xF6" // yMin = -10
            . "\x00\x00" // xMax
            . "\x01\xF4" // yMax = 500
            . "\x00\x00";
        $instance = $this->buildTrueType($glyf . str_repeat("\x00", 32), [
            'Ascent' => 800,
            'Descent' => -200,
            'ctgdata' => [120 => 1],
            'indexToLoc' => [1 => 0],
            'table' => ['glyf' => ['length' => 42, 'offset' => 0]],
            'urk' => 1.0,
        ]);

        $this->invokeMethod($instance, 'getHeights');
        $fontData = $this->getFontData($instance);

        $this->assertSame(500, $this->getFontDataInt($fontData, 'XHeight'), 'yMax, not yMax - yMin');
    }

    // -------------------------------------------------------------------------
    // cmap format 2: integer sub-header keys and out-of-range reads
    // -------------------------------------------------------------------------

    /**
     * Assemble a cmap format 2 subtable.
     *
     * @param array<int, int>                                  $keys      subHeaderKeys by high byte
     * @param array<int, array{0: int, 1: int, 2: int, 3: int}> $headers   firstCode, entryCount, idDelta, idRangeOffset
     * @param array<int, int>                                  $glyphs    glyphIndexArray
     */
    private function buildFormat2(array $keys, array $headers, array $glyphs): string
    {
        $subHeaderKeys = '';
        for ($chr = 0; $chr < 256; ++$chr) {
            $subHeaderKeys .= pack('n', $keys[$chr] ?? 0);
        }

        $subHeaders = '';
        foreach ($headers as $header) {
            $subHeaders .= pack('n4', $header[0], $header[1], $header[2], $header[3]);
        }

        $glyphIndexArray = '';
        foreach ($glyphs as $glyph) {
            $glyphIndexArray .= pack('n', $glyph);
        }

        return (
            "\x00\x02" // format = 2
            . "\x02\x18" // length (unused)
            . "\x00\x00" // language (unused)
            . $subHeaderKeys
            . $subHeaders
            . $glyphIndexArray
        );
    }

    /**
     * The mandatory 0xFFFF -> 0xFFFF terminating segment of a format 4 subtable closes the
     * table and is not recorded, so U+FFFF is not mapped.
     */
    public function testProcessFormat4DropsTheTerminatingSegment(): void
    {
        // two segments: 0x41..0x42 mapped by idDelta, then the 0xFFFF terminator
        $font =
            "\x00\x04" // format = 4
            . "\x00\x20" // length = 32
            . "\x00\x00" // language
            . "\x00\x04" // segCountX2 = 4 (2 segments)
            . "\x00\x04\x00\x01\x00\x00" // searchRange, entrySelector, rangeShift
            . "\x00\x42\xff\xff" // endCode
            . "\x00\x00" // reservedPad
            . "\x00\x41\xff\xff" // startCode
            . "\x00\x0a\x00\x01" // idDelta
            . "\x00\x00\x00\x00"; // idRangeOffset

        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(75, $this->getCtgGlyph($fontData, 0x41));
        $this->assertSame(76, $this->getCtgGlyph($fontData, 0x42));
        $this->assertNull($this->getCtgGlyph($fontData, 0xFFFF));
        // only the two mapped codes and the .notdef fallback
        $this->assertCount(3, $this->getCtgData($fontData));
    }

    /**
     * A byte encoded font is one whose character codes all fit a single byte, so the
     * highest code is checked as well as the number of cmap entries.
     */
    public function testUnicodeTypeSurvivesACmapOf256CodesAboveTheByteRange(): void
    {
        // one segment covering U+0100..U+01FE (255 codes) plus the terminator: with the
        // .notdef fallback that is exactly 256 ctgdata entries
        $font =
            "\x00\x04" // format = 4
            . "\x00\x20" // length = 32
            . "\x00\x00" // language
            . "\x00\x04" // segCountX2 = 4 (2 segments)
            . "\x00\x04\x00\x01\x00\x00" // searchRange, entrySelector, rangeShift
            . "\x01\xfe\xff\xff" // endCode
            . "\x00\x00" // reservedPad
            . "\x01\x00\xff\xff" // startCode
            . "\x00\x0a\x00\x01" // idDelta
            . "\x00\x00\x00\x00"; // idRangeOffset

        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertCount(256, $this->getCtgData($fontData));
        $this->assertSame('TrueTypeUnicode', $this->getFontDataString($fontData, 'type'));
    }

    /**
     * The subrange of a format 6 subtable is addressed with uint16 codes, so an entry
     * running past the BMP is not a character code of the subtable and is dropped.
     */
    public function testProcessFormat6DropsEntriesAboveTheBmp(): void
    {
        $font =
            "\x00\x06" // format = 6
            . "\x00\x00" // length (unused)
            . "\x00\x00" // language (unused)
            . "\xff\xfe" // firstCode = 65534
            . "\x00\x03" // entryCount = 3
            . "\x00\x0a" // glyph for 0xFFFE
            . "\x00\x0b" // glyph for 0xFFFF
            . "\x00\x0c"; // glyph for 0x10000, which does not exist in this format

        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(10, $this->getCtgGlyph($fontData, 0xFFFE));
        $this->assertSame(11, $this->getCtgGlyph($fontData, 0xFFFF));
        $this->assertNull($this->getCtgGlyph($fontData, 0x1_0000));
    }

    /**
     * The synthetic font of these cases is the cmap table alone, so the declared length
     * covers the whole of it unless a case states otherwise.
     *
     * @param int $cmapLength Length declared by the table directory for the cmap table.
     *
     * @return array<string, mixed>
     */
    private function cmapFdt(int $cmapLength = 0x1_0000): array
    {
        return [
            'encodingTables' => [
                ['platformID' => 3, 'encodingID' => 1, 'offset' => 0],
            ],
            'platform_id' => 3,
            'encoding_id' => 1,
            'table' => ['cmap' => ['offset' => 0, 'length' => $cmapLength]],
            'type' => 'TrueTypeUnicode',
        ];
    }

    public function testProcessFormat2HandlesSubHeaderKeysThatAreNotMultiplesOfEight(): void
    {
        // subHeaderKeys[2] = 10. The spec says the value is "subHeader index x 8", and a
        // value that is not an exact multiple is divided down: 10 selects subHeader 1.
        $font = $this->buildFormat2(
            [2 => 10],
            [
                [0, 1, 0, 10], // subHeader 0 → adjusted idRangeOffset 0
                [0, 2, 0, 2], // subHeader 1 → adjusted idRangeOffset 0
            ],
            [99, 10, 11],
        );

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        // high byte 2 uses subHeader 1: two low bytes mapped through glyphIndexArray
        $this->assertSame(99, $this->getCtgGlyph($fontData, 2 << 8));
        $this->assertSame(10, $this->getCtgGlyph($fontData, (2 << 8) + 1));
        // every other high byte has subHeaderKey 0 → single-byte code mapped through
        // subHeader 0, which only declares the code 0
        $this->assertSame(99, $this->getCtgGlyph($fontData, 0));
        $this->assertSame(0, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(0, $this->getCtgGlyph($fontData, 255));
    }

    /**
     * Sub-headers may address overlapping ranges of the shared glyph index array, so the
     * sum of their entry counts over-estimates its size and the array stops at the end of
     * the cmap table.
     */
    public function testProcessFormat2StopsTheGlyphIndexArrayAtTheEndOfTheFont(): void
    {
        // two sub-headers declaring 4 entries each, over a glyph index array of 4
        $font = $this->buildFormat2(
            [2 => 8],
            [
                [0, 4, 0, 10],
                [0, 4, 0, 2],
            ],
            [99, 10, 11, 12],
        );

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        // the four entries the font carries are read, and the four the sub-headers
        // declare past the end of it are not looked for
        $this->assertSame(99, $this->getCtgGlyph($fontData, 2 << 8));
        $this->assertSame(10, $this->getCtgGlyph($fontData, (2 << 8) + 1));
        $this->assertSame(11, $this->getCtgGlyph($fontData, (2 << 8) + 2));
        $this->assertSame(12, $this->getCtgGlyph($fontData, (2 << 8) + 3));
    }

    public function testProcessFormat2FallsBackToNotdefForOutOfRangeIdRangeOffset(): void
    {
        // subHeader 1 points 99 entries into a 2-entry glyphIndexArray, as a truncated or
        // hostile cmap does
        $font = $this->buildFormat2(
            [2 => 8],
            [
                [0, 1, 0, 10],
                [0, 1, 0, 200], // adjusted: (200-2)/2 = 99, far past the array
            ],
            [99, 10],
        );

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        // the two-byte code resolves to notdef
        $this->assertSame(0, $this->getCtgGlyph($fontData, 2 << 8));
        // the single-byte code declared by subHeader 0 still resolves normally
        $this->assertSame(99, $this->getCtgGlyph($fontData, 0));
    }

    public function testProcessFormat2FallsBackToNotdefForAnEmptyGlyphIndexArray(): void
    {
        // entryCount 0 everywhere → subHeader 0 declares no code at all, so every
        // single-byte code falls outside its range and resolves to notdef.
        $font = $this->buildFormat2([], [[0, 0, 0, 2]], []);

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(0, $this->getCtgGlyph($fontData, 65));
    }

    public function testProcessFormat2RejectsAnOversizedGlyphIndexArray(): void
    {
        // 18 sub-headers of 65535 entries each declare more glyph indexes than there are
        // Unicode code points: the array is bounded before it is allocated.
        $headers = [];
        for ($idx = 0; $idx < 18; ++$idx) {
            $headers[] = [0, 65_535, 0, 2];
        }

        $font = $this->buildFormat2([1 => 17 * 8], $headers, []);

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->assertThrowsMessage(
            FontException::class,
            'too many glyph indexes',
            fn() => $this->invokeMethod($instance, 'getCIDToGIDMap'),
        );
    }

    public function testProcessFormat2KeepsASubHeaderInsideItsOwnHighByte(): void
    {
        // high byte 2 declares 65535 low bytes, and the run stops at 0x02FF
        $glyphs = \array_fill(0, 300, 7);
        $font = $this->buildFormat2([2 => 8], [[0, 0, 0, 2], [0, 65_535, 0, 2]], $glyphs);

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        // the last code of the high byte is mapped
        $this->assertSame(7, $this->getCtgGlyph($fontData, (2 << 8) + 255));
        // the code blocks of the high bytes that follow it carry no entry at all
        $this->assertNull($this->getCtgGlyph($fontData, 3 << 8));
        $this->assertNull($this->getCtgGlyph($fontData, (3 << 8) + 1));
    }

    // -------------------------------------------------------------------------
    // cmap format 4: out-of-range glyphIdArray reads
    // -------------------------------------------------------------------------

    /**
     * Assemble a cmap format 4 subtable with a single segment.
     *
     * @param array<int, int> $glyphs glyphIdArray
     */
    private function buildFormat4(int $startCode, int $endCode, int $idDelta, int $idRangeOffset, array $glyphs): string
    {
        $glyphIdArray = '';
        foreach ($glyphs as $glyph) {
            $glyphIdArray .= pack('n', $glyph);
        }

        // 24 bytes of header + segment arrays, plus the glyph index array
        $length = 24 + strlen($glyphIdArray);

        return (
            "\x00\x04" // format = 4
            . pack('n', $length) // length
            . "\x00\x00" // language (unused)
            . "\x00\x02" // segCountX2 = 2 → segCount = 1
            . "\x00\x00\x00\x00\x00\x00" // searchRange, entrySelector, rangeShift
            . pack('n', $endCode) // endCount[0]
            . "\x00\x00" // reservedPad
            . pack('n', $startCode) // startCount[0]
            . pack('n', $idDelta) // idDelta[0]
            . pack('n', $idRangeOffset) // idRangeOffset[0]
            . $glyphIdArray
        );
    }

    public function testProcessFormat4MapsCharsThroughTheGlyphIdArray(): void
    {
        // idRangeOffset 2 → gid index = 2/2 + (chr - startCode) - (segCount - kdx)
        //                            = 1 + (chr - 65) - 1 = chr - 65
        $font = $this->buildFormat4(65, 66, 0, 2, [99, 10]);

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(99, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(10, $this->getCtgGlyph($fontData, 66));
    }

    public function testProcessFormat4FallsBackToNotdefForOutOfRangeIdRangeOffset(): void
    {
        // idRangeOffset 100 points 49 entries into an empty glyphIdArray
        $font = $this->buildFormat4(65, 65, 0, 100, []);

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(0, $this->getCtgGlyph($fontData, 65));
    }

    public function testProcessFormat4KeepsTheNotdefEntriesOfTheGlyphIdArray(): void
    {
        // a zero entry of the glyphIdArray encodes missingGlyph and is not shifted by
        // idDelta, so the code point stays notdef
        $font = $this->buildFormat4(65, 66, 100, 2, [7, 0]);

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(107, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(0, $this->getCtgGlyph($fontData, 66));
    }

    /**
     * The glyph index array is sized from the declared subtable length and stops at the end
     * of the cmap table.
     */
    public function testProcessFormat4StopsTheGlyphIdArrayAtTheEndOfTheCmapTable(): void
    {
        // the subtable declares two glyphIdArray entries but carries none of them: the two
        // that follow belong to the next table of the font
        $subtable = $this->buildFormat4(65, 66, 0, 2, []);
        $subtable = substr_replace($subtable, pack('n', strlen($subtable) + 4), 2, 2);
        $font = $subtable . pack('n', 4444) . pack('n', 5555);

        $instance = $this->buildTrueType($font, $this->cmapFdt(strlen($subtable)));
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        // the codes resolve to notdef rather than to the glyphs of the neighbouring table
        $this->assertSame(0, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(0, $this->getCtgGlyph($fontData, 66));
    }

    /**
     * The array also stops at the end of the buffer, so a subtable truncated by the file is
     * read as far as it goes.
     */
    public function testProcessFormat4StopsTheGlyphIdArrayAtTheEndOfTheFont(): void
    {
        // the length declares four entries, only the first two are in the file
        $font = $this->buildFormat4(65, 66, 0, 2, [99, 10]);
        $font = substr_replace($font, pack('n', strlen($font) + 4), 2, 2);

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(99, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(10, $this->getCtgGlyph($fontData, 66));
    }

    public function testProcessFormat4UsesIdDeltaWhenIdRangeOffsetIsZero(): void
    {
        // idRangeOffset 0 → gid = (idDelta + chr) % 65536, glyphIdArray untouched
        $font = $this->buildFormat4(65, 66, 10, 0, []);

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(75, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(76, $this->getCtgGlyph($fontData, 66));
    }

    // -------------------------------------------------------------------------
    // cmap entry budget (formats 8, 12 and 13)
    // -------------------------------------------------------------------------

    /**
     * Assemble a subtable for one of the 32-bit cmap formats.
     *
     * @param array<int, array{0: int, 1: int, 2: int}> $groups startCharCode, endCharCode, glyph
     */
    private function buildGroupedCmap(int $format, array $groups, string $is32 = ''): string
    {
        // format 8 carries an 8192-byte is32 bitmap between the header and the groups
        if ($format === 8 && $is32 === '') {
            $is32 = \str_repeat("\x00", 8192);
        }

        $body = '';
        foreach ($groups as $group) {
            $body .= \pack('N3', $group[0], $group[1], $group[2]);
        }

        return (
            \pack('n', $format)
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x00" // length (unused)
            . "\x00\x00\x00\x00" // language (unused)
            . $is32
            . \pack('N', \count($groups)) // numGroups
            . $body
        );
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function groupedCmapFormatProvider(): array
    {
        return [
            'format 8' => [8],
            'format 12' => [12],
            'format 13' => [13],
        ];
    }

    /**
     * The number of code points a subtable may map is bounded, so a group claiming the
     * whole 32-bit code space is refused.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('groupedCmapFormatProvider')]
    public function testGroupedCmapRejectsAGroupWiderThanTheEntryBudget(int $format): void
    {
        // one full-range group exactly exhausts the budget, so a second one overruns it
        $font = $this->buildGroupedCmap($format, [[0, 0xFFFF_FFFF, 1], [0, 0, 1]]);
        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->assertThrowsMessage(
            FontException::class,
            'too many code points',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'getCIDToGIDMap'),
        );
    }

    /**
     * The budget follows the number of glyphs the loca table accounts for, so a subtable
     * declaring more codes than that is refused.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('groupedCmapFormatProvider')]
    public function testGroupedCmapRejectsMoreCodePointsThanTheGlyphCountAllows(int $format): void
    {
        // 100 glyphs allow 800 code points, and this group states one more
        $font = $this->buildGroupedCmap($format, [[0, 800, 1]]);
        $instance = $this->buildTrueType($font, ['tot_num_glyphs' => 100] + $this->cmapFdt());

        $this->assertThrowsMessage(
            FontException::class,
            'too many code points',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'getCIDToGIDMap'),
        );
    }

    /**
     * The same subtable is read when the font carries the glyphs to justify it.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('groupedCmapFormatProvider')]
    public function testGroupedCmapAcceptsTheCodePointsTheGlyphCountAllows(int $format): void
    {
        $font = $this->buildGroupedCmap($format, [[0, 799, 1]]);
        $instance = $this->buildTrueType($font, ['tot_num_glyphs' => 100] + $this->cmapFdt());

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(1, $this->getCtgGlyph($fontData, 0));
        // formats 8 and 12 walk the glyph indexes of the range, format 13 repeats the first
        $this->assertSame($format === 13 ? 1 : 800, $this->getCtgGlyph($fontData, 799));
    }

    /**
     * A font accounting for no glyph at all still maps the code space of the byte encoded
     * formats, which do not depend on the glyph count.
     */
    public function testAByteEncodedSubtableIsReadWithoutAnyGlyphAccountedFor(): void
    {
        // format 0, length 262, language 0, then one glyph index for each of the 256 codes
        $font = "\x00\x00\x01\x06\x00\x00" . \str_repeat("\x07", 256);
        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(7, $this->getCtgGlyph($fontData, 255));
    }

    /**
     * A group whose end precedes its start maps nothing and does not give the entry budget
     * back. Formats 8 and 13 share the same accounting.
     */
    public function testGroupedCmapDoesNotCreditTheBudgetForAReversedGroup(): void
    {
        $font = $this->buildGroupedCmap(12, [
            [0x10_FFFF, 0x0000,    1], // reversed: maps nothing
            [0x0000,    0x10_FFFF, 1], // one full pass: exactly the budget
            [0x0000,    0x0000,    1], // one entry too many
        ]);
        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->assertThrowsMessage(
            FontException::class,
            'too many code points',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'getCIDToGIDMap'),
        );
    }

    /**
     * A group that starts beyond the last Unicode codepoint maps nothing at all.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('groupedCmapFormatProvider')]
    public function testGroupedCmapSkipsAGroupAboveTheUnicodeMaximum(int $format): void
    {
        $font = $this->buildGroupedCmap($format, [[0x11_0000, 0x11_0010, 1]]);
        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        // only the seeded .notdef entry survives
        $this->assertSame([0 => 0], $this->getCtgData($fontData));
    }

    /**
     * The is32 bitmap of format 8 only records whether a group wrote its bounds as 16 or
     * 32 bit values, so a code point above the BMP is mapped as it is read.
     */
    public function testProcessFormat8MapsA32BitCodePointDirectly(): void
    {
        $cpw = 0x1_0000;
        $font = $this->buildGroupedCmap(8, [[$cpw, $cpw, 5]]);

        $instance = $this->buildTrueType($font, $this->cmapFdt());
        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(5, $this->getCtgGlyph($fontData, $cpw));
    }

    /**
     * A subtable that ends inside the 8192-byte is32 bitmap is truncated and must be
     * rejected rather than read past its end.
     */
    public function testProcessFormat8RejectsATruncatedIs32Bitmap(): void
    {
        $font = $this->buildGroupedCmap(8, [[65, 65, 1]], \str_repeat("\x00", 4096));

        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->bcExpectException(\RangeException::class);
        $this->invokeMethod($instance, 'getCIDToGIDMap');
    }

    // -------------------------------------------------------------------------
    // name string decoding
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{0: int, 1: int, 2: string, 3: string}>
     */
    public static function nameEncodingProvider(): array
    {
        return [
            // platformId, encodingId, raw bytes, expected UTF-8
            'unicode platform is UTF-16BE' => [0, 3, "\x00\x41\x00\x42", 'AB'],
            'windows simplified chinese' => [3, 3, "\x41\x42", 'AB'],
            'windows traditional chinese' => [3, 4, "\x41\x42", 'AB'],
            'windows korean' => [3, 5, "\x41\x42", 'AB'],
            'windows default is UTF-16BE' => [3, 10, "\x00\x41", 'A'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nameEncodingProvider')]
    public function testConvertStringEncodingHandlesEveryPlatformEncodingPair(
        int $platformId,
        int $encodingId,
        string $raw,
        string $expected,
    ): void {
        $instance = $this->buildTrueType('', []);
        $this->assertSame($expected, $this->convertStringEncoding($instance, $raw, $platformId, $encodingId));
    }

    /**
     * The sanitizer strips everything outside [A-Za-z0-9_-], so a name made only of
     * punctuation leaves nothing usable.
     */
    public function testGetNameRejectsANameThatSanitizesToNothing(): void
    {
        // name table: format(2) count(2) stringOffset(2), then one NameRecord (12 bytes)
        $nameTable =
            "\x00\x00" // format
            . "\x00\x01" // count = 1
            . "\x00\x12" // stringStorageOffset = 18
            . "\x00\x03" // platformID = 3
            . "\x00\x01" // encodingID = 1
            . "\x00\x00" // languageID
            . "\x00\x06" // nameID = 6 (PostScript name)
            . "\x00\x08" // string length
            . "\x00\x00" // string offset
            . "\x00\x2B\x00\x2B\x00\x2B\x00\x2B"; // '++++' in UTF-16BE

        $instance = $this->buildTrueType($nameTable, [
            'table' => ['name' => ['offset' => 0, 'length' => \strlen($nameTable)]],
        ]);

        $this->assertThrowsMessage(
            FontException::class,
            'Error getting font name',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'getFontName'),
        );
    }

    // -------------------------------------------------------------------------
    // cmap entry budgets and OS/2 metrics
    // -------------------------------------------------------------------------

    /**
     * Assemble a cmap format 4 subtable from a list of [startCode, endCode] segments,
     * all mapping through idDelta with no glyph index array.
     *
     * @param array<int, array{0: int, 1: int}> $segments
     */
    private function buildFormat4Segments(array $segments): string
    {
        $segCount = \count($segments);
        $end = '';
        $start = '';
        $delta = '';
        $range = '';
        foreach ($segments as $segment) {
            $end .= \pack('n', $segment[1]);
            $start .= \pack('n', $segment[0]);
            $delta .= \pack('n', 0);
            $range .= \pack('n', 0);
        }

        $length = 16 + (8 * $segCount);

        return (
            "\x00\x04" // format = 4
            . \pack('n', $length)
            . "\x00\x00" // language
            . \pack('n', $segCount * 2) // segCountX2
            . "\x00\x00\x00\x00\x00\x00" // searchRange, entrySelector, rangeShift
            . $end
            . "\x00\x00" // reservedPad
            . $start
            . $delta
            . $range
        );
    }

    /**
     * Segments may overlap and each may span the whole BMP, so a subtable of a few
     * hundred bytes can otherwise describe billions of mappings.
     */
    public function testProcessFormat4RejectsMoreCodePointsThanTheBudget(): void
    {
        // 32 full-BMP segments are 2 million mappings, past the 0x110000 budget
        $segments = \array_fill(0, 32, [0, 0xFFFF]);
        $font = $this->buildFormat4Segments($segments);
        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->assertThrowsMessage(
            FontException::class,
            'too many code points',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'getCIDToGIDMap'),
        );
    }

    public function testProcessFormat4AcceptsASegmentWithinTheBudget(): void
    {
        $font = $this->buildFormat4Segments([[65, 66]]);
        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(65, $this->getCtgGlyph($fontData, 65));
        $this->assertSame(66, $this->getCtgGlyph($fontData, 66));
    }

    /**
     * Assemble a cmap format 10 subtable.
     *
     * @param array<int, int> $glyphs
     */
    private function buildFormat10(int $startCharCode, int $numChars, array $glyphs): string
    {
        $glyphIdArray = '';
        foreach ($glyphs as $glyph) {
            $glyphIdArray .= \pack('n', $glyph);
        }

        return (
            "\x00\x0a" // format = 10
            . "\x00\x00" // reserved
            . "\x00\x00\x00\x00" // length (unused)
            . "\x00\x00\x00\x00" // language (unused)
            . \pack('N', $startCharCode)
            . \pack('N', $numChars)
            . $glyphIdArray
        );
    }

    public function testProcessFormat10RejectsMoreCodePointsThanTheBudget(): void
    {
        $font = $this->buildFormat10(0, 0xFFFF_FFFF, []);
        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->assertThrowsMessage(
            FontException::class,
            'too many code points',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'getCIDToGIDMap'),
        );
    }

    /**
     * Like the other uint32-based formats, a code point past the last Unicode one is
     * consumed but not mapped.
     */
    public function testProcessFormat10SkipsCodePointsAboveTheUnicodeMaximum(): void
    {
        $font = $this->buildFormat10(0x10_FFFF, 2, [7, 8]);
        $instance = $this->buildTrueType($font, $this->cmapFdt());

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);

        $this->assertSame(7, $this->getCtgGlyph($fontData, 0x10_FFFF));
        $this->assertNull($this->getCtgGlyph($fontData, 0x11_0000));
    }

    /**
     * usWeightClass is a weight class in the 1..1000 range, not a value in font design
     * units, so the stem estimate does not depend on unitsPerEm.
     */
    public function testGetOS2MetricsDoesNotScaleTheWeightClass(): void
    {
        $font =
            "\x00\x04" // version = 4
            . "\x04\x00" // xAvgCharWidth = 1024 raw units
            . "\x02\xbc" // usWeightClass = 700 (bold)
            . "\x00\x05" // usWidthClass (unused)
            . "\x00\x08"; // fsType = Editable

        $instance = $this->buildTrueType($font, [
            'table' => [
                'OS/2' => ['offset' => 0, 'length' => 10],
            ],
            'urk' => 1000 / 2048, // a 2048 unitsPerEm font
            'subset' => true,
        ]);

        $this->invokeMethod($instance, 'getOS2Metrics');
        $fontData = $this->getFontData($instance);

        // StemV = round(70*700/400) = 123, StemH = round(30*700/400) = 53
        $this->assertSame(123, $this->getFontDataInt($fontData, 'StemV'));
        $this->assertSame(53, $this->getFontDataInt($fontData, 'StemH'));
        // the average width is a font-unit value and is still scaled
        $this->assertSame(500, $this->getFontDataInt($fontData, 'AvgWidth'));
    }

    /**
     * A weight class outside the 1..1000 range of the spec is clamped.
     */
    public function testGetOS2MetricsClampsAnOutOfRangeWeightClass(): void
    {
        $font =
            "\x00\x04"
            . "\x00\x00"
            . "\xff\xff" // usWeightClass = 65535
            . "\x00\x05"
            . "\x00\x08";

        $instance = $this->buildTrueType($font, [
            'table' => [
                'OS/2' => ['offset' => 0, 'length' => 10],
            ],
            'urk' => 1.0,
            'subset' => true,
        ]);

        $this->invokeMethod($instance, 'getOS2Metrics');
        $fontData = $this->getFontData($instance);

        // clamped to 1000: StemV = round(70*1000/400) = 175
        $this->assertSame(175, $this->getFontDataInt($fontData, 'StemV'));
    }

    /**
     * A font written on the legacy 1..9 weight scale rounds the stem estimate down to
     * zero, which is floored at one.
     */
    public function testGetOS2MetricsKeepsAStemWidthForALowWeightClass(): void
    {
        $font =
            "\x00\x04"
            . "\x00\x00"
            . "\x00\x01" // usWeightClass = 1, the legacy scale
            . "\x00\x05"
            . "\x00\x08";

        $instance = $this->buildTrueType($font, [
            'table' => [
                'OS/2' => ['offset' => 0, 'length' => 10],
            ],
            'urk' => 1.0,
            'subset' => true,
        ]);

        $this->invokeMethod($instance, 'getOS2Metrics');
        $fontData = $this->getFontData($instance);

        // round(70*1/400) and round(30*1/400) are both zero before the floor
        $this->assertSame(1, $this->getFontDataInt($fontData, 'StemV'));
        $this->assertSame(1, $this->getFontDataInt($fontData, 'StemH'));
    }

    /**
     * xAvgCharWidth is a signed field and is the fallback of the default width, which
     * would move the text backwards if it were negative.
     */
    public function testGetOS2MetricsFloorsANegativeAverageWidth(): void
    {
        $font =
            "\x00\x04"
            . "\xff\x00" // xAvgCharWidth = -256
            . "\x01\x90" // usWeightClass = 400
            . "\x00\x05"
            . "\x00\x08";

        $instance = $this->buildTrueType($font, [
            'table' => [
                'OS/2' => ['offset' => 0, 'length' => 10],
            ],
            'urk' => 1.0,
            'subset' => true,
        ]);

        $this->invokeMethod($instance, 'getOS2Metrics');

        $this->assertSame(0, $this->getFontDataInt($this->getFontData($instance), 'AvgWidth'));
    }

    // -------------------------------------------------------------------------
    // name record selection
    // -------------------------------------------------------------------------

    /**
     * Assemble a name table from a list of [nameID, string] records, all Windows/UCS-2.
     *
     * @param array<int, array{0: int, 1: string}> $records
     */
    private function buildNameTable(array $records): string
    {
        $count = \count($records);
        $storage = '';
        $entries = '';
        foreach ($records as $record) {
            $encoded = \implode('', \array_map(
                static fn(string $chr): string => "\x00" . $chr,
                \str_split($record[1]),
            ));
            $entries .=
                "\x00\x03" // platformID = 3
                . "\x00\x01" // encodingID = 1
                . "\x00\x00" // languageID
                . \pack('n', $record[0]) // nameID
                . \pack('n', \strlen($encoded)) // string length
                . \pack('n', \strlen($storage)); // string offset
            $storage .= $encoded;
        }

        return (
            "\x00\x00" // format
            . \pack('n', $count)
            . \pack('n', 6 + (12 * $count)) // stringStorageOffset
            . $entries
            . $storage
        );
    }

    public function testGetFontNamePrefersThePostScriptRecord(): void
    {
        $nameTable = $this->buildNameTable([[1, 'Family'], [4, 'Family-Full'], [6, 'PostScriptName']]);

        $instance = $this->buildTrueType($nameTable, [
            'table' => ['name' => ['offset' => 0, 'length' => \strlen($nameTable)]],
        ]);

        $this->invokeMethod($instance, 'getFontName');
        $fontData = $this->getFontData($instance);

        $this->assertSame('PostScriptName', $fontData['name'] ?? null);
    }

    /**
     * A font without a PostScript name record falls back to the full name (nameID 4) and
     * then to the family name (nameID 1).
     */
    public function testGetFontNameFallsBackToTheFullNameRecord(): void
    {
        $nameTable = $this->buildNameTable([[1, 'Family'], [4, 'Family-Full']]);

        $instance = $this->buildTrueType($nameTable, [
            'table' => ['name' => ['offset' => 0, 'length' => \strlen($nameTable)]],
        ]);

        $this->invokeMethod($instance, 'getFontName');
        $fontData = $this->getFontData($instance);

        $this->assertSame('Family-Full', $fontData['name'] ?? null);
    }

    public function testGetFontNameFallsBackToTheFamilyNameRecord(): void
    {
        $nameTable = $this->buildNameTable([[0, 'Copyright'], [1, 'Family']]);

        $instance = $this->buildTrueType($nameTable, [
            'table' => ['name' => ['offset' => 0, 'length' => \strlen($nameTable)]],
        ]);

        $this->invokeMethod($instance, 'getFontName');
        $fontData = $this->getFontData($instance);

        $this->assertSame('Family', $fontData['name'] ?? null);
    }

    /**
     * A record whose string is not entirely inside the name table describes no name, so the
     * bytes of whatever table follows are not read as one.
     */
    public function testGetFontNameRejectsAStringOutsideTheNameTable(): void
    {
        $nameTable = $this->buildNameTable([[6, 'PostScriptName']]);
        // the record is readable from the font buffer, but reaches past the name table
        $trailer = $nameTable . 'NEIGHBOURINGTABLEBYTES';

        $instance = $this->buildTrueType($trailer, [
            'table' => ['name' => ['offset' => 0, 'length' => \strlen($nameTable) - 4]],
        ]);

        $this->assertThrowsMessage(
            FontException::class,
            'font name',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'getFontName'),
        );
    }

    /**
     * A PostScript record whose string lies outside the storage area leaves nothing usable,
     * so the scan goes on and the fallbacks already collected name the font.
     */
    public function testGetFontNameSkipsAnUnreadablePostScriptRecord(): void
    {
        $nameTable = $this->buildNameTable([[1, 'Family'], [4, 'Family-Full'], [6, 'PostScriptName']]);
        // point the string offset of the nameID 6 record past the end of the table
        $record = 6 + (12 * 2);
        $broken = \substr_replace($nameTable, \pack('n', 0xFFFF), $record + 10, 2);

        $instance = $this->buildTrueType($broken, [
            'table' => ['name' => ['offset' => 0, 'length' => \strlen($broken)]],
        ]);

        $this->invokeMethod($instance, 'getFontName');
        $fontData = $this->getFontData($instance);

        $this->assertSame('Family-Full', $fontData['name'] ?? null);
    }

    /**
     * The records of a font are commonly repeated for several platforms, and a broken one
     * does not hide the copy that follows it.
     */
    public function testGetFontNameUsesAlaterPostScriptRecord(): void
    {
        $nameTable = $this->buildNameTable([[6, 'PostScriptName'], [6, 'OtherPlatform']]);
        $broken = \substr_replace($nameTable, \pack('n', 0xFFFF), 6 + 10, 2);

        $instance = $this->buildTrueType($broken, [
            'table' => ['name' => ['offset' => 0, 'length' => \strlen($broken)]],
        ]);

        $this->invokeMethod($instance, 'getFontName');
        $fontData = $this->getFontData($instance);

        $this->assertSame('OtherPlatform', $fontData['name'] ?? null);
    }

    public function testGetFontNameRejectsANameTableWithoutAUsableRecord(): void
    {
        $nameTable = $this->buildNameTable([[0, 'Copyright'], [5, 'Version 1.0']]);

        $instance = $this->buildTrueType($nameTable, [
            'table' => ['name' => ['offset' => 0, 'length' => \strlen($nameTable)]],
        ]);

        $this->assertThrowsMessage(
            FontException::class,
            'Error getting font name',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'getFontName'),
        );
    }

    // -------------------------------------------------------------------------
    // table directory bounds
    // -------------------------------------------------------------------------

    /**
     * A table record pointing outside the font file is rejected by the bounds check,
     * before any parsing takes place.
     */
    public function testCheckTableBoundsRejectsARecordPastTheEndOfTheFile(): void
    {
        $instance = $this->buildTrueType(\str_repeat("\x00", 64), [
            'table' => [
                'head' => ['offset' => 0, 'length' => 54],
                'loca' => ['offset' => 54, 'length' => 4096],
            ],
        ]);

        $this->assertThrowsMessage(
            FontException::class,
            'Font table out of bounds: loca',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'checkTableBounds'),
        );
    }

    public function testCheckTableBoundsAcceptsRecordsInsideTheFile(): void
    {
        $instance = $this->buildTrueType(\str_repeat("\x00", 64), [
            'table' => [
                'head' => ['offset' => 0, 'length' => 54],
                'loca' => ['offset' => 54, 'length' => 10],
            ],
        ]);

        $this->invokeMethod($instance, 'checkTableBounds');
        $this->assertTrue(true, 'a table directory inside the file is accepted');
    }

    /**
     * The fixed-size headers of head, hhea, maxp, post, cmap, name and hmtx are addressed
     * as raw file offsets, so a record declaring a shorter table is refused.
     */
    public function testCheckTableBoundsRejectsAnUndersizedFixedTable(): void
    {
        $instance = $this->buildTrueType(\str_repeat("\x00", 256), [
            'table' => [
                'head' => ['offset' => 0, 'length' => 0],
            ],
        ]);

        $this->assertThrowsMessage(
            FontException::class,
            'Font table too short: head',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'checkTableBounds'),
        );
    }

    public function testCheckTableBoundsRejectsATruncatedHheaTable(): void
    {
        $instance = $this->buildTrueType(\str_repeat("\x00", 256), [
            'table' => [
                'head' => ['offset' => 0, 'length' => 54],
                'hhea' => ['offset' => 54, 'length' => 35],
            ],
        ]);

        $this->assertThrowsMessage(
            FontException::class,
            'Font table too short: hhea',
            /** @throws \Throwable */
            fn() => $this->invokeMethod($instance, 'checkTableBounds'),
        );
    }
}
