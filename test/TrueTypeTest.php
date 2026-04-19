<?php

/**
 * TrueTypeTest.php
 *
 * @since     2011-05-23
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
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TrueTypeTest extends TestUtil
{
    public function testGetCIDToGIDMapFormat13SetsNotDefGlyph(): void
    {
        // Format 13 subtable with numGroups=0: maps nothing, only .notdef fallback is added.
        $font = "\x00\x0d"           // format = 13
            . "\x00\x00"             // reserved
            . "\x00\x00\x00\x10"    // length = 16
            . "\x00\x00\x00\x00"    // language
            . "\x00\x00\x00\x00";   // numGroups = 0

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

        $this->assertSame([0 => 0], $fontData['ctgdata']);
        $this->assertSame('TrueTypeUnicode', $fontData['type']);
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
        $font = "\x00\x0d"               // format = 13
            . "\x00\x00"                 // reserved
            . "\x00\x00\x00\x1c"        // length
            . "\x00\x00\x00\x00"        // language
            . "\x00\x00\x00\x01"        // numGroups = 1
            . "\x00\x00\x00\x41"        // startCharCode = 65
            . "\x00\x00\x00\x43"        // endCharCode   = 67
            . "\x00\x00\x00\x05";       // glyphID       = 5

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
        $this->assertSame(5, $fontData['ctgdata'][65]);
        $this->assertSame(5, $fontData['ctgdata'][66]);
        $this->assertSame(5, $fontData['ctgdata'][67]);
        // .notdef fallback must be present
        $this->assertSame(0, $fontData['ctgdata'][0]);
    }

    public function testProcessFormat13AddsGlyphsToSubset(): void
    {
        $font = "\x00\x0d"
            . "\x00\x00"
            . "\x00\x00\x00\x1c"
            . "\x00\x00\x00\x00"
            . "\x00\x00\x00\x01"
            . "\x00\x00\x00\x41"        // startCharCode = 65
            . "\x00\x00\x00\x41"        // endCharCode   = 65
            . "\x00\x00\x00\x07";       // glyphID       = 7

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
        $subGlyphs = $this->getProperty($instance, 'subglyphs');

        $this->assertSame(7, $fontData['ctgdata'][65]);
        $this->assertArrayHasKey(7, $subGlyphs);
        $this->assertTrue($subGlyphs[7]);
    }


    public function testProcessFormat14NonDefaultUVSMapsGlyphs(): void
    {
        // Format 14 subtable with 1 VariationSelector record that has a Non-Default UVS table.
        //
        // Byte layout (subtable starts at offset 0):
        //   0- 1: format              = 14  (0x00,0x0E) — consumed by getCIDToGIDMap before this call
        //   2- 5: length              = 30  (0x00,0x00,0x00,0x1E)
        //   6- 9: numVarSelectorRecs  =  1  (0x00,0x00,0x00,0x01)
        //  10-12: varSelector         = 0x0E0100 (3 bytes)
        //  13-16: defaultUVSOffset    =  0  (absent)
        //  17-20: nonDefaultUVSOffset = 21  (0x00,0x00,0x00,0x15) — relative to subtable start
        //  21-24: numUVSMappings      =  1  (0x00,0x00,0x00,0x01)
        //  25-27: unicodeValue        = U+0082A6 (0x00,0x82,0xA6)
        //  28-29: glyphID             = 1142 (0x04,0x76)
        $font = "\x00\x0e"               // format = 14
            . "\x00\x00\x00\x1e"         // length = 30
            . "\x00\x00\x00\x01"         // numVarSelectorRecords = 1
            . "\x0e\x01\x00"             // varSelector = U+E0100 (uint24)
            . "\x00\x00\x00\x00"         // defaultUVSOffset = 0 (absent)
            . "\x00\x00\x00\x15"         // nonDefaultUVSOffset = 21
            . "\x00\x00\x00\x01"         // numUVSMappings = 1
            . "\x00\x82\xa6"             // unicodeValue = U+0082A6 (uint24)
            . "\x04\x76";                // glyphID = 1142

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

        // Non-default UVS mapping: U+0082A6 → glyph 1142
        $this->assertSame(1142, $fontData['ctgdata'][0x0082A6]);
        // .notdef fallback must still be set
        $this->assertSame(0, $fontData['ctgdata'][0]);
    }

    public function testProcessFormat14NonDefaultUVSTracksSubglyphs(): void
    {
        // Same layout as above but with a second mapping and subchars tracking.
        //
        // Byte layout (subtable starts at offset 0):
        //   0- 1: format              = 14  (0x00,0x0E)
        //   2- 5: length              = 35
        //   6- 9: numVarSelectorRecs  =  1
        //  10-12: varSelector         = 0x0E0101 (3 bytes)
        //  13-16: defaultUVSOffset    =  0
        //  17-20: nonDefaultUVSOffset = 21
        //  21-24: numUVSMappings      =  2
        //  25-27: unicodeValue[0]     = U+0082A6 → glyphID 7961  (0x00,0x82,0xA6 + 0x1F,0x19)
        //  30-32: unicodeValue[1]     = U+004E4D → glyphID 42    (0x00,0x4E,0x4D + 0x00,0x2A)
        $font = "\x00\x0e"               // format = 14
            . "\x00\x00\x00\x25"         // length = 37
            . "\x00\x00\x00\x01"         // numVarSelectorRecords = 1
            . "\x0e\x01\x01"             // varSelector = U+E0101 (uint24)
            . "\x00\x00\x00\x00"         // defaultUVSOffset = 0
            . "\x00\x00\x00\x15"         // nonDefaultUVSOffset = 21
            . "\x00\x00\x00\x02"         // numUVSMappings = 2
            . "\x00\x82\xa6"             // unicodeValue[0] = U+0082A6
            . "\x1f\x19"                 // glyphID[0] = 7961
            . "\x00\x4e\x4d"             // unicodeValue[1] = U+004E4D
            . "\x00\x2a";                // glyphID[1] = 42

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

        // Mark U+0082A6 as a subset char to verify subglyphs tracking
        $this->setProperty($instance, 'subchars', [0x0082A6 => true]);

        $this->invokeMethod($instance, 'getCIDToGIDMap');
        $fontData = $this->getFontData($instance);
        $subGlyphs = $this->getProperty($instance, 'subglyphs');

        $this->assertSame(7961, $fontData['ctgdata'][0x0082A6]);
        $this->assertSame(42, $fontData['ctgdata'][0x004E4D]);
        // Glyph 7961 must be in the subset (0x0082A6 was a subchar)
        $this->assertArrayHasKey(7961, $subGlyphs);
        $this->assertTrue($subGlyphs[7961]);
        // Glyph 42 must NOT be in the subset (U+004E4D was not a subchar)
        $this->assertArrayNotHasKey(42, $subGlyphs);
    }

    public function testProcessFormat14DefaultUVSOnlyAddsNoCtgEntries(): void
    {
        // Format 14 subtable with 1 VariationSelector record that has only a Default UVS table.
        // Default UVS sequences use the standard cmap glyph — no ctgdata entries should be added.
        //
        // Byte layout (subtable starts at offset 0):
        //   0- 1: format              = 14
        //   2- 5: length              = 26
        //   6- 9: numVarSelectorRecs  =  1
        //  10-12: varSelector         = 0x0E0100
        //  13-16: defaultUVSOffset    = 21  (has a Default UVS table)
        //  17-20: nonDefaultUVSOffset = 0   (absent)
        //  21-24: numUnicodeValueRanges = 1
        //  25-27: startUnicodeValue   = U+004E4D (uint24)
        //  28:    additionalCount     = 2
        $font = "\x00\x0e"               // format = 14
            . "\x00\x00\x00\x1d"         // length = 29
            . "\x00\x00\x00\x01"         // numVarSelectorRecords = 1
            . "\x0e\x01\x00"             // varSelector = U+E0100 (uint24)
            . "\x00\x00\x00\x15"         // defaultUVSOffset = 21
            . "\x00\x00\x00\x00"         // nonDefaultUVSOffset = 0 (absent)
            . "\x00\x00\x00\x01"         // numUnicodeValueRanges = 1
            . "\x00\x4e\x4d"             // startUnicodeValue = U+004E4D (uint24)
            . "\x02";                    // additionalCount = 2

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

        // Default UVS adds no explicit ctgdata entries beyond .notdef
        $this->assertSame([0 => 0], $fontData['ctgdata']);
    }

    public function testGetCIDToGIDMapFormat14SetsNotDefGlyph(): void
    {
        // Format 14 subtable with numVarSelectorRecords=0: no mappings → only .notdef fallback added.
        $font = "\x00\x0e"               // format = 14
            . "\x00\x00\x00\x0a"         // length = 10
            . "\x00\x00\x00\x00";        // numVarSelectorRecords = 0

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

        $this->assertSame([0 => 0], $fontData['ctgdata']);
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

        $this->bcExpectException('\\' . FontException::class);
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
        $subGlyphs = $this->getProperty($instance, 'subglyphs');

        $this->assertSame(7, $fontData['ctgdata'][65]);
        $this->assertArrayHasKey(7, $subGlyphs);
        $this->assertTrue($subGlyphs[7]);
    }

    /**
     * @param array<string, mixed> $fdt
     */
    protected function buildTrueType(string $font, array $fdt): TrueType
    {
        $class = new \ReflectionClass(TrueType::class);
        $instance = $class->newInstanceWithoutConstructor();

        $this->setProperty($instance, 'font', $font);
        $this->setProperty($instance, 'fdt', $fdt);
        $this->setProperty($instance, 'fbyte', new Byte($font));
        $this->setProperty($instance, 'offset', 0);

        return $instance;
    }

    /**
     * @param array<int, mixed> $args
     */
    protected function invokeMethod(TrueType $instance, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod(TrueType::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($instance, $args);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFontData(TrueType $instance): array
    {
        $fontData = $this->getProperty($instance, 'fdt');
        $this->assertIsArray($fontData);

        return $fontData;
    }

    protected function getProperty(TrueType $instance, string $name): mixed
    {
        $property = new \ReflectionProperty(TrueType::class, $name);
        $property->setAccessible(true);

        return $property->getValue($instance);
    }

    protected function setProperty(TrueType $instance, string $name, mixed $value): void
    {
        $property = new \ReflectionProperty(TrueType::class, $name);
        $property->setAccessible(true);
        $property->setValue($instance, $value);
    }
}
