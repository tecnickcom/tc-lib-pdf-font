<?php

/**
 * TypeOneInternalsTest.php
 *
 * @since     2026-05-05
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

use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Import\TypeOne;

/**
 * Tests for protected methods of Import\TypeOne exercised via reflection.
 *
 * @since     2026-05-05
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @phpstan-import-type TFontData from \Com\Tecnick\Pdf\Font\Load
 */
class TypeOneInternalsTest extends TestUtil
{
    /** @var TFontData */
    private static array $fdtDefaults = [
        'Ascender' => 0,
        'Ascent' => 700,
        'AvgWidth' => 0.0,
        'CapHeight' => 680,
        'CharacterSet' => '',
        'Descender' => -200,
        'Descent' => -200,
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
        'cbboxu' => [],
        'cidinfo' => ['Ordering' => '', 'Registry' => '', 'Supplement' => 0, 'uni2cid' => []],
        'compress' => false,
        'ctg' => '',
        'ctgdata' => [],
        'ctgu' => [],
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
        'gidenc' => false,
        'i' => 0,
        'ifile' => '',
        'indexToLoc' => [],
        'input_file' => '',
        'isUnicode' => false,
        'italicAngle' => 0,
        'key' => '',
        'lenIV' => 4,
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
        'type' => 'Type1',
        'underlinePosition' => 0,
        'underlineThickness' => 0,
        'unicode' => false,
        'unitsPerEm' => 0,
        'up' => 0,
        'urk' => 1.0,
        'usedgid' => [],
        'ut' => 0,
        'weight' => 'normal',
    ];

    private function buildTypeOne(): TypeOne
    {
        $class = new \ReflectionClass(TypeOne::class);
        $instance = $class->newInstanceWithoutConstructor();
        $this->setProp($instance, 'fdt', self::$fdtDefaults);
        $this->setProp($instance, 'font', '');
        return $instance;
    }

    /**
     * @param array<int, mixed> $args
     */
    private function callMethod(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);
        return $ref->invokeArgs($obj, $args);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function callIntMethod(object $obj, string $method, array $args = []): int
    {
        return $this->expectInt($this->callMethod($obj, $method, $args), 'Expected int result.');
    }

    /**
     * Invoke getCids() and return the character codes it reports.
     *
     * @param array<int, mixed> $args
     *
     * @return array<int, int>
     */
    private function callCidsMethod(object $obj, array $args): array
    {
        return $this->expectIntList($this->callMethod($obj, 'getCids', $args), 'Expected a list of character codes.');
    }

    /** @return array<int, int> */
    private function expectIntList(mixed $value, string $message): array
    {
        if (!\is_array($value)) {
            $this->fail($message);
        }

        /** @var array<int, int> $value */
        return $value;
    }

    /**
     * @param array<int, mixed> $args
     *
     * @return array<int, array<int, string>>
     */
    private function callArrayMethod(object $obj, string $method, array $args = []): array
    {
        return $this->expectIntStringMatrix($this->callMethod($obj, $method, $args), 'Expected array result.');
    }

    private function setProp(object $obj, string $name, mixed $value): void
    {
        $prop = new \ReflectionProperty($obj, $name);
        $prop->setValue($obj, $value);
    }

    /** @return array<string, mixed> */
    private function getFontData(object $obj): array
    {
        $prop = new \ReflectionProperty($obj, 'fdt');
        return $this->expectFontData($prop->getValue($obj));
    }

    /** @param array<string, mixed> $fontData */
    private function getFontIntValue(array $fontData, string $key): int
    {
        if (!isset($fontData[$key]) || !\is_int($fontData[$key])) {
            $this->fail('Expected int font field: ' . $key);
        }

        return $fontData[$key];
    }

    /**
     * @param array<string, mixed> $fontData
     *
     * @return array<int, string>
     */
    private function getFontStringMap(array $fontData, string $key): array
    {
        if (!isset($fontData[$key]) || !\is_array($fontData[$key])) {
            $this->fail('Expected array font field: ' . $key);
        }

        /** @var array<int, string> $value */
        $value = $fontData[$key];
        return $value;
    }

    private function expectInt(mixed $value, string $message): int
    {
        if (!\is_int($value)) {
            $this->fail($message);
        }

        return $value;
    }

    /** @return array<int, array<int, string>> */
    private function expectIntStringMatrix(mixed $value, string $message): array
    {
        if (!\is_array($value)) {
            $this->fail($message);
        }

        /** @var array<int, array<int, string>> $value */
        return $value;
    }

    /** @return array<string, mixed> */
    private function expectFontData(mixed $value): array
    {
        if (!\is_array($value)) {
            $this->fail('Expected font data array.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /** @param array<string, mixed> $fontData */
    private function getFontStringValue(array $fontData, string $key): string
    {
        if (!isset($fontData[$key]) || !\is_string($fontData[$key])) {
            $this->fail('Expected string font field: ' . $key);
        }

        return $fontData[$key];
    }

    // -------------------------------------------------------------------------
    // extractFontInfo
    // -------------------------------------------------------------------------

    public function testExtractFontInfoReadsAFontBBoxWithIrregularSpacing(): void
    {
        $instance = $this->buildTypeOne();
        // real fonts align the values with runs of spaces or wrap them over two lines
        $this->setProp($instance, 'font', "/FontName /Test-Font def\n/FontBBox {-168  -218\n1000 898} def");

        $this->callMethod($instance, 'extractFontInfo');
        $fdt = $this->getFontData($instance);

        $this->assertSame('-168 -218 1000 898', $this->getFontStringValue($fdt, 'bbox'));
        $this->assertSame(898, $this->getFontIntValue($fdt, 'Ascent'));
        $this->assertSame(-218, $this->getFontIntValue($fdt, 'Descent'));
    }

    public function testExtractFontInfoRejectsANameMadeOnlyOfStrippedCharacters(): void
    {
        $instance = $this->buildTypeOne();
        $this->setProp($instance, 'font', "/FontName /+++ def\n/FontBBox {0 -200 1000 700} def");

        // nothing survives the sanitisation, so there is no name to write as /BaseFont
        $this->assertThrowsMessage(
            FontException::class,
            'Unable to extract font name',
            fn() => $this->callMethod($instance, 'extractFontInfo'),
        );
    }

    public function testExtractFontInfoDefaultsAnEmptyFontBBoxToZero(): void
    {
        $instance = $this->buildTypeOne();
        $this->setProp($instance, 'font', "/FontName /Test-Font def\n/FontBBox {  } def");

        $this->callMethod($instance, 'extractFontInfo');
        $fdt = $this->getFontData($instance);

        $this->assertSame('0 0 0 0', $this->getFontStringValue($fdt, 'bbox'));
    }

    /**
     * The italic angle is a real number, and the italic bit of the font descriptor is
     * derived from it, so the fractional part takes part in the reading.
     */
    public function testExtractFontInfoReadsAFractionalItalicAngle(): void
    {
        $instance = $this->buildTypeOne();
        $this->setProp(
            $instance,
            'font',
            "/FontName /Test-Font def\n/FontBBox {0 -200 1000 700} def\n/ItalicAngle -12.5 def",
        );

        $this->callMethod($instance, 'extractFontInfo');
        $fdt = $this->getFontData($instance);

        $this->assertSame(-13, $this->getFontIntValue($fdt, 'italicAngle'), 'rounded away from zero');
        $this->assertSame(64, $this->getFontIntValue($fdt, 'Flags') & 64, 'the italic bit');
    }

    public function testExtractFontInfoParsesWeight(): void
    {
        $instance = $this->buildTypeOne();
        $this->setProp(
            $instance,
            'font',
            "/FontName /Helvetica-Bold def\n/FontBBox {0 -200 1000 700} def\n/Weight (Bold) def",
        );

        $this->callMethod($instance, 'extractFontInfo');
        $fdt = $this->getFontData($instance);

        // the parsed /Weight drives the bold StemV heuristic in extractStem
        $this->assertSame('bold', $this->getFontStringValue($fdt, 'weight'));
    }

    public function testExtractFontInfoDefaultsWeightToBook(): void
    {
        $instance = $this->buildTypeOne();
        $this->setProp($instance, 'font', "/FontName /Helvetica def\n/FontBBox {0 -200 1000 700} def");

        $this->callMethod($instance, 'extractFontInfo');
        $fdt = $this->getFontData($instance);

        $this->assertSame('Book', $this->getFontStringValue($fdt, 'weight'));
    }

    // -------------------------------------------------------------------------
    // extractStem
    // -------------------------------------------------------------------------

    public function testExtractStemReadsStdVwAndStdHw(): void
    {
        $instance = $this->buildTypeOne();
        $eplain = '/StdVW [85] def /StdHW [40] def /CapHeight [690] def';
        $this->callMethod($instance, 'extractStem', [$eplain]);
        $fdt = $this->getFontData($instance);

        $this->assertSame(85, $this->getFontIntValue($fdt, 'StemV'));
        $this->assertSame(40, $this->getFontIntValue($fdt, 'StemH'));
        $this->assertSame(690, $this->getFontIntValue($fdt, 'CapHeight'));
    }

    public function testExtractStemUsesBoldDefaultWhenStdVwAbsent(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['weight'] = 'bold';
        $this->setProp($instance, 'fdt', $fdt);

        $this->callMethod($instance, 'extractStem', ['']);
        $fdt = $this->getFontData($instance);

        $this->assertSame(123, $this->getFontIntValue($fdt, 'StemV'));
        $this->assertSame(30, $this->getFontIntValue($fdt, 'StemH'));
    }

    public function testExtractStemUsesDefaultsWhenNoMatchingKeys(): void
    {
        $instance = $this->buildTypeOne();
        $this->callMethod($instance, 'extractStem', ['']);
        $fdt = $this->getFontData($instance);

        $this->assertSame(70, $this->getFontIntValue($fdt, 'StemV'));
        $this->assertSame(30, $this->getFontIntValue($fdt, 'StemH'));
        // CapHeight falls back to Ascent (700)
        $this->assertSame(700, $this->getFontIntValue($fdt, 'CapHeight'));
    }

    // -------------------------------------------------------------------------
    // readLenIV
    // -------------------------------------------------------------------------

    public function testReadLenIVDefaultsToFourWhenMissing(): void
    {
        $instance = $this->buildTypeOne();
        $this->callMethod($instance, 'readLenIV', ['no lenIV here']);
        $fdt = $this->getFontData($instance);
        $this->assertSame(4, $this->getFontIntValue($fdt, 'lenIV'));
    }

    public function testReadLenIVParsesExplicitValue(): void
    {
        $instance = $this->buildTypeOne();
        $this->callMethod($instance, 'readLenIV', ['/lenIV 8 def']);
        $fdt = $this->getFontData($instance);
        $this->assertSame(8, $this->getFontIntValue($fdt, 'lenIV'));
    }

    public function testReadLenIVParsesZero(): void
    {
        $instance = $this->buildTypeOne();
        $this->callMethod($instance, 'readLenIV', ['/lenIV 0 def']);
        $fdt = $this->getFontData($instance);
        $this->assertSame(0, $this->getFontIntValue($fdt, 'lenIV'));
    }

    /**
     * At least one digit is required, so an entry carrying no number, or a negative one,
     * keeps the specified default of 4.
     */
    public function testReadLenIVKeepsTheDefaultWhenNoNumberFollows(): void
    {
        foreach (['/lenIVX 8 def', '/lenIV -1 def', '/lenIV def'] as $entry) {
            $instance = $this->buildTypeOne();
            $this->callMethod($instance, 'readLenIV', [$entry]);
            $fdt = $this->getFontData($instance);
            $this->assertSame(4, $this->getFontIntValue($fdt, 'lenIV'), $entry);
        }
    }

    // -------------------------------------------------------------------------
    // getCharstringData
    // -------------------------------------------------------------------------

    public function testGetCharstringDataReturnsEmptyMatchesWhenNoneFound(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc'] = '';
        $this->setProp($instance, 'fdt', $fdt);

        $eplain = '/CharStrings 0 dict dup begin end';
        $result = $this->callArrayMethod($instance, 'getCharstringData', [$eplain]);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetCharstringDataReturnsEmptyWhenEncNotInMap(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc'] = 'nonexistent_encoding';
        $this->setProp($instance, 'fdt', $fdt);

        $eplain = '/CharStrings 0 dict dup begin end';
        $result = $this->callArrayMethod($instance, 'getCharstringData', [$eplain]);
        $this->assertIsArray($result);
    }

    /**
     * The declared byte count delimits the charstring, whose encrypted data may itself
     * contain the ' ND' terminator.
     */
    public function testGetCharstringDataTakesTheDeclaredNumberOfBytes(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc'] = '';
        $this->setProp($instance, 'fdt', $fdt);

        // the charstring of 'A' carries the bytes of a terminator halfway through it
        $binary = "\x11\x22 ND\x33\x44";
        $eplain =
            '/CharStrings 2 dict dup begin'
            . "\n/A "
            . \strlen($binary)
            . ' RD '
            . $binary
            . " ND\n"
            . "/B 3 RD \x55\x66\x77 ND\n"
            . 'end';

        $result = $this->callArrayMethod($instance, 'getCharstringData', [$eplain]);

        $this->assertCount(2, $result);
        $this->assertSame('A', $result[0][1] ?? null);
        $this->assertSame($binary, $result[0][2] ?? null);
        $this->assertSame('B', $result[1][1] ?? null);
        $this->assertSame("\x55\x66\x77", $result[1][2] ?? null);
    }

    /**
     * '_' and '-' are legal PostScript name characters (ligature names such as 'f_i'), so
     * the glyph name class accepts anything but whitespace and the delimiters.
     */
    public function testGetCharstringDataReadsLigatureAndHyphenatedGlyphNames(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc'] = '';
        $this->setProp($instance, 'fdt', $fdt);

        $eplain =
            '/CharStrings 3 dict dup begin'
            . "\n/f_i 2 RD \x11\x22 ND"
            . "\n/uni0041-alt 2 RD \x33\x44 ND"
            . "\n/A 2 RD \x55\x66 ND"
            . "\nend";

        $result = $this->callArrayMethod($instance, 'getCharstringData', [$eplain]);

        $this->assertCount(3, $result);
        $this->assertSame('f_i', $result[0][1] ?? null);
        $this->assertSame("\x11\x22", $result[0][2] ?? null);
        $this->assertSame('uni0041-alt', $result[1][1] ?? null);
        $this->assertSame('A', $result[2][1] ?? null);
    }

    /**
     * A length running past the end of the dictionary ends the scan: the entries collected
     * so far are kept, and the truncated one is not.
     */
    public function testGetCharstringDataStopsAtATruncatedEntry(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc'] = '';
        $this->setProp($instance, 'fdt', $fdt);

        $eplain = "/CharStrings 2 dict dup begin\n/A 2 RD \x11\x22 ND\n/B 4096 RD \x33\x44";
        $result = $this->callArrayMethod($instance, 'getCharstringData', [$eplain]);

        $this->assertCount(1, $result);
        $this->assertSame('A', $result[0][1] ?? null);
    }

    /**
     * The values may be separated by any run of whitespace, and the capture starts right
     * after the bracket.
     */
    public function testExtractEplainInfoReadsSpacedBlueValues(): void
    {
        foreach (['/BlueValues[-20 0 683 704 710 731]', "/BlueValues [ -20 0 683\n704 710 731 ]"] as $spelling) {
            $instance = $this->buildTypeOne();
            $fdt = self::$fdtDefaults;
            // extractEplainInfo() decodes the section itself, so the fixture is encrypted
            $fdt['encrypted'] = $this->encryptEexec($spelling . ' def /CharStrings 0 dict dup begin end');
            $this->setProp($instance, 'fdt', $fdt);

            $this->callMethod($instance, 'extractEplainInfo');
            $fdt = $this->getFontData($instance);

            $this->assertSame(683, $this->getFontIntValue($fdt, 'XHeight'), $spelling);
            $this->assertSame(710, $this->getFontIntValue($fdt, 'CapHeight'), $spelling);
        }
    }

    /**
     * Encrypt a clear-text eexec section, so that getEplain() decodes it back.
     */
    private function encryptEexec(string $plain): string
    {
        $csr = 55_665;
        $cc1 = 52_845;
        $cc2 = 22_719;
        $out = '';
        $len = \strlen($plain);
        for ($idx = 0; $idx < $len; ++$idx) {
            $chr = \ord($plain[$idx]) ^ ($csr >> 8);
            $out .= \chr($chr);
            $csr = ((($chr + $csr) * $cc1) + $cc2) % 65_536;
        }

        return $out;
    }

    public function testGetCharstringDataPopulatesEncMapForKnownEncoding(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc'] = 'cp1252';
        $this->setProp($instance, 'fdt', $fdt);

        $eplain = '/CharStrings 0 dict dup begin end';
        $this->callMethod($instance, 'getCharstringData', [$eplain]);
        $fdt = $this->getFontData($instance);

        $this->assertNotEmpty($this->getFontStringMap($fdt, 'enc_map'));
    }

    // -------------------------------------------------------------------------
    // getCids
    // -------------------------------------------------------------------------

    public function testGetCidsReturnsImapValueWhenCharNameFound(): void
    {
        $instance = $this->buildTypeOne();
        $imap = ['A' => 65, 'B' => 66];
        $val = [0 => '', 1 => 'A', 2 => ''];
        $this->assertSame([65], $this->callCidsMethod($instance, [$imap, $val]));
    }

    /**
     * The internal encoding array of a font assigns each code once, but nothing in the file
     * bounds the value: a code it cannot address is no code at all.
     */
    public function testGetCidsRejectsAnImapValueOutsideTheSingleByteRange(): void
    {
        $instance = $this->buildTypeOne();
        $imap = ['Big' => 300];
        $val = [0 => '', 1 => 'Big', 2 => ''];
        $this->assertSame([], $this->callCidsMethod($instance, [$imap, $val]));
    }

    /**
     * An unencoded glyph has no character code, so no width is recorded for it.
     */
    public function testGetCidsReturnsNothingWhenEncMapEmpty(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc_map'] = [];
        $this->setProp($instance, 'fdt', $fdt);

        $imap = [];
        $val = [0 => '', 1 => 'Z', 2 => ''];
        $this->assertSame([], $this->callCidsMethod($instance, [$imap, $val]));
    }

    public function testGetCidsReturnsNothingWhenCharNotFoundInEncMap(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc_map'] = ['a' => 97];
        $this->setProp($instance, 'fdt', $fdt);

        $imap = [];
        $val = [0 => '', 1 => 'missing', 2 => ''];
        $this->assertSame([], $this->callCidsMethod($instance, [$imap, $val]));
    }

    public function testGetCidsRejectsACodeOutsideTheSingleByteRange(): void
    {
        $instance = $this->buildTypeOne();
        // Some encoding maps ('symbol' among them) name glyphs above the single-byte
        // range: a Type1 font cannot address them, so they have no character code.
        $encMap = [];
        $encMap[1001] = 'BigChar';
        $fdt = self::$fdtDefaults;
        $fdt['enc_map'] = $encMap;
        $this->setProp($instance, 'fdt', $fdt);

        $imap = [];
        $val = [0 => '', 1 => 'BigChar', 2 => ''];
        $this->assertSame([], $this->callCidsMethod($instance, [$imap, $val]));
    }

    public function testGetCidsKeepsTheLastSingleByteCode(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc_map'] = [255 => 'LastChar'];
        $this->setProp($instance, 'fdt', $fdt);

        $imap = [];
        $val = [0 => '', 1 => 'LastChar', 2 => ''];
        $this->assertSame([255], $this->callCidsMethod($instance, [$imap, $val]));
    }

    /**
     * WinAnsi gives 'space' both 32 and 160 and 'hyphen' both 45 and 173: reporting only
     * the first left the no-break space and the soft hyphen without a width.
     */
    public function testGetCidsReturnsEveryCodeTheEncodingGivesAName(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc_map'] = [160 => 'space', 32 => 'space', 45 => 'hyphen'];
        $this->setProp($instance, 'fdt', $fdt);

        $imap = [];
        $val = [0 => '', 1 => 'space', 2 => ''];
        // ascending, so that the charstring is always decoded under the same code
        $this->assertSame([32, 160], $this->callCidsMethod($instance, [$imap, $val]));
    }

    /**
     * '.notdef' is the name of the glyph a code falls back to, not a character.
     */
    public function testGetCidsReturnsNothingForNotdef(): void
    {
        $instance = $this->buildTypeOne();
        $imap = ['.notdef' => 0];
        $val = [0 => '', 1 => '.notdef', 2 => ''];
        $this->assertSame([], $this->callCidsMethod($instance, [$imap, $val]));
    }

    // -------------------------------------------------------------------------
    // decodeNumber
    // -------------------------------------------------------------------------

    public function testDecodeNumberHandlesByte32To246(): void
    {
        $instance = $this->buildTypeOne();
        // ccom[0] = 139 → decoded value = 139 - 139 = 0
        $ccom = [139];
        /** @var array<int, int> $cdec */
        $cdec = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 0;
        $cid = 0;
        $newIdx = $this->callIntMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        /** @var array<int, int> $cdec */
        $this->assertSame(1, $newIdx);
        $this->assertSame(0, $cdec[0] ?? null);
    }

    public function testDecodeNumberHandlesBytes247To250(): void
    {
        $instance = $this->buildTypeOne();
        // ccom[0] = 247, ccom[1] = 0 → value = (247-247)*256 + 0 + 108 = 108
        $ccom = [247, 0];
        /** @var array<int, int> $cdec */
        $cdec = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 0;
        $cid = 0;
        $newIdx = $this->callIntMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        /** @var array<int, int> $cdec */
        $this->assertSame(2, $newIdx);
        $this->assertSame(108, $cdec[0] ?? null);
    }

    public function testDecodeNumberHandlesBytes251To254(): void
    {
        $instance = $this->buildTypeOne();
        // ccom[0] = 251, ccom[1] = 0 → value = -(251-251)*256 - 0 - 108 = -108
        $ccom = [251, 0];
        /** @var array<int, int> $cdec */
        $cdec = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 0;
        $cid = 0;
        $newIdx = $this->callIntMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        /** @var array<int, int> $cdec */
        $this->assertSame(2, $newIdx);
        $this->assertSame(-108, $cdec[0] ?? null);
    }

    public function testDecodeNumberHandlesByte255FourByteInt(): void
    {
        $instance = $this->buildTypeOne();
        // ccom[0]=255, ccom[1..4] = big-endian int 500 = 0x000001F4
        $ccom = [255, 0, 0, 1, 0xF4];
        /** @var array<int, int> $cdec */
        $cdec = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 0;
        $cid = 0;
        $newIdx = $this->callIntMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        /** @var array<int, int> $cdec */
        $this->assertSame(5, $newIdx);
        // Type 1 charstring 255 operands are 32-bit big-endian: the value is exactly 500
        // on every platform. unpack('l') would have read the machine byte order instead.
        $this->assertSame(500, $cdec[0] ?? null);
    }

    /**
     * Operands with |v| > 1131 cannot use the compact 247..254 encoding, so they are the
     * ones that actually travel through the 255 path.
     *
     * @return array<string, array{0: array<int, int>, 1: int}>
     */
    public static function bigEndianOperandProvider(): array
    {
        return [
            'zero' => [[0x00, 0x00, 0x00, 0x00], 0],
            'small positive' => [[0x00, 0x00, 0x01, 0xF4], 500],
            'above the compact range' => [[0x00, 0x00, 0x11, 0x94], 4500],
            'large positive' => [[0x00, 0x01, 0x86, 0xA0], 100_000],
            'max positive' => [[0x7F, 0xFF, 0xFF, 0xFF], 2_147_483_647],
            'minus one' => [[0xFF, 0xFF, 0xFF, 0xFF], -1],
            'negative' => [[0xFF, 0xFE, 0x79, 0x60], -100_000],
            'min negative' => [[0x80, 0x00, 0x00, 0x00], -2_147_483_648],
        ];
    }

    /**
     * @param array<int, int> $bytes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('bigEndianOperandProvider')]
    public function testDecodeNumberReadsByte255OperandsAsBigEndianSigned(array $bytes, int $expected): void
    {
        $instance = $this->buildTypeOne();
        $ccom = [255, ...$bytes];
        /** @var array<int, int> $cdec */
        $cdec = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 0;
        $cid = 0;
        $newIdx = $this->callIntMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);

        $this->assertSame(5, $newIdx);
        /** @var array<int, int> $cdec */
        $this->assertSame($expected, $cdec[0] ?? null);
    }

    public function testDecodeNumberThrowsOnTruncatedByte255Operand(): void
    {
        $instance = $this->buildTypeOne();
        // only three of the four operand bytes are present
        $ccom = [255, 0x00, 0x00, 0x01];
        /** @var array<int, int> $cdec */
        $cdec = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 0;
        $cid = 0;

        $this->bcExpectException(\Com\Tecnick\Pdf\Font\Exception::class);
        $this->callMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
    }

    public function testDecodeNumberByte255WidthReachesHsbw(): void
    {
        // an hsbw width above the compact-encoding range, assembled by the 255 path as a
        // 32-bit big-endian value
        $instance = $this->buildTypeOne();
        // 'sbx wx hsbw': the sidebearing is the compact encoding of zero
        $ccom = [139, 255, 0x00, 0x00, 0x11, 0x94, 13];
        /** @var array<int, int> $cdec */
        $cdec = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 0;
        $cid = 3;

        $idx = $this->callIntMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);

        // the caller advances the operand stack pointer between operands
        $cck = 1;
        $idx = $this->callIntMethod($instance, 'decodeNumber', [$idx, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        /** @var array<int, int> $cdec */
        $this->assertSame(4500, $cdec[1] ?? null);

        $cck = 2;
        $this->callMethod($instance, 'decodeNumber', [$idx, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);

        /** @var array<int, int> $cwidths */
        $this->assertSame(4500, $cwidths[3] ?? null);
    }

    public function testDecodeNumberHsbwCommandUpdatesWidth(): void
    {
        $instance = $this->buildTypeOne();
        // 'sbx wx hsbw': with the two operands on the stack the width is the last of them
        $ccom = [13]; // hsbw opcode (value < 32)
        /** @var array<int, int> $cdec */
        $cdec = [0 => 40, 1 => 300];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 2; // stack holds the sidebearing and the width
        $cid = 7;
        $this->callMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        /** @var array<int, int> $cwidths */
        $this->assertArrayHasKey(7, $cwidths);
        $this->assertSame(300, $cwidths[7] ?? null);
    }

    /**
     * 'hsbw' takes 'sbx wx': with a single operand on the stack the value there is the
     * sidebearing, and recording it would give the glyph the wrong advance width.
     */
    public function testDecodeNumberHsbwIgnoresAnUndersuppliedOperandList(): void
    {
        $instance = $this->buildTypeOne();
        $ccom = [13];
        /** @var array<int, int> $cdec */
        $cdec = [0 => 40];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 1;
        $cid = 7;
        $this->callMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        /** @var array<int, int> $cwidths */
        $this->assertSame([], $cwidths);
    }

    /**
     * 'sbw' takes 'sbx sby wx wy', so the horizontal width is the third of the four.
     */
    public function testDecodeNumberSbwCommandUpdatesWidth(): void
    {
        $instance = $this->buildTypeOne();
        $ccom = [12, 7]; // the escaped 'sbw' command
        /** @var array<int, int> $cdec */
        $cdec = [0 => 10, 1 => 20, 2 => 300, 3 => 0];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 4;
        $cid = 7;
        $this->callMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        /** @var array<int, int> $cwidths */
        $this->assertSame(300, $cwidths[7] ?? null);
    }

    /**
     * With fewer than four operands the value 'sbw' would read is a sidebearing.
     */
    public function testDecodeNumberSbwIgnoresAnUndersuppliedOperandList(): void
    {
        $instance = $this->buildTypeOne();
        $ccom = [12, 7];
        /** @var array<int, int> $cdec */
        $cdec = [0 => 10, 1 => 20, 2 => 300];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 3;
        $cid = 7;
        $this->callMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        /** @var array<int, int> $cwidths */
        $this->assertSame([], $cwidths);
    }

    // -------------------------------------------------------------------------
    // getInternalMap
    // -------------------------------------------------------------------------

    /**
     * The entries of the built-in encoding are separated by runs of whitespace, which a
     * font may align with spaces or wrap over two lines.
     */
    public function testGetInternalMapReadsEntriesWithIrregularSpacing(): void
    {
        $instance = $this->buildTypeOne();
        $this->setProp(
            $instance,
            'font',
            "/Encoding 256 array\n"
            . "dup 32 /space put\n" // the canonical spacing
            . "dup 65/A put\n" // no space before the glyph name
            . "dup  66  /B  put\n" // aligned with runs of spaces
            . "dup 67 /C\nput\n", // wrapped over two lines
        );

        /** @var mixed $imap */
        $imap = $this->callMethod($instance, 'getInternalMap');
        $this->assertIsArray($imap);

        $this->assertSame(32, $imap['space'] ?? null);
        $this->assertSame(65, $imap['A'] ?? null);
        $this->assertSame(66, $imap['B'] ?? null);
        $this->assertSame(67, $imap['C'] ?? null);
    }

    // -------------------------------------------------------------------------
    // storeFontData: error paths
    // -------------------------------------------------------------------------

    public function testStoreFontDataThrowsOnInvalidMarker(): void
    {
        $instance = $this->buildTypeOne();
        // First byte not 128 → invalid binary Type1
        $this->setProp($instance, 'font', "\x00\x00\x00\x00\x00\x00");
        $this->bcExpectException(FontException::class);
        $this->callMethod($instance, 'storeFontData', []);
    }
}
