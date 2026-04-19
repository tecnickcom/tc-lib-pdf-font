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


    public function testGetCIDToGIDMapFormat14SetsNotDefGlyph(): void
    {
        $instance = $this->buildTrueType("\x00\x0e", [
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
