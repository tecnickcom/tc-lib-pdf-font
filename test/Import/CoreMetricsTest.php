<?php

/**
 * CoreMetricsTest.php
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

namespace Test\Import;

use Com\Tecnick\File\Exception as FileException;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Import\Core;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Core Metrics Test
 *
 * Verifies that Import\Core maps the AFM widths of Helvetica to the WinAnsi
 * byte positions (cw) and to the Unicode codepoints (cwu).
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
class CoreMetricsTest extends TestCase
{
    private const HELVETICA_AFM = __DIR__ . '/../../util/vendor/tecnickcom/tc-font-mirror/core/Helvetica.afm';

    /** @var TFontData */
    private static array $fdtTemplate = [
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
        'usedgid' => [],
        'ut' => 0,
        'weight' => '',
    ];

    /** @return array<string, array{int, int}> */
    public static function provideHelveticaWinAnsiWidths(): array
    {
        return [
            // [ winansi_cid, expected_width ]
            // printable ASCII
            'space' => [0x20, 278],
            'A' => [0x41, 667],
            'hyphen' => [0x2D, 333],
            // WinAnsi 0x80-0x9F range
            'Euro' => [0x80, 556],
            'quotesinglbase' => [0x82, 222],
            'florin' => [0x83, 556],
            'quotedblbase' => [0x84, 333],
            'ellipsis' => [0x85, 1000],
            'dagger' => [0x86, 556],
            'daggerdbl' => [0x87, 556],
            'circumflex' => [0x88, 333],
            'perthousand' => [0x89, 1000],
            'Scaron' => [0x8A, 667],
            'guilsinglleft' => [0x8B, 333],
            'OE' => [0x8C, 1000],
            'Zcaron' => [0x8E, 611],
            'quoteleft' => [0x91, 222],
            'quoteright' => [0x92, 222],
            'quotedblleft' => [0x93, 333],
            'quotedblright' => [0x94, 333],
            'bullet' => [0x95, 350],
            'endash' => [0x96, 556],
            'emdash' => [0x97, 1000],
            'tilde' => [0x98, 333],
            'trademark' => [0x99, 1000],
            'scaron' => [0x9A, 500],
            'guilsinglright' => [0x9B, 333],
            'oe' => [0x9C, 944],
            'zcaron' => [0x9E, 500],
            'Ydieresis' => [0x9F, 667],
        ];
    }

    /**
     * @throws FileException
     * @throws FontException
     */
    #[DataProvider('provideHelveticaWinAnsiWidths')]
    public function testHelveticaWinAnsiWidth(int $cid, int $expected): void
    {
        $fdt = $this->getHelveticaMetrics();
        $this->assertSame($expected, $fdt['cw'][$cid] ?? null, 'cw[0x' . \dechex($cid) . ']');
    }

    /** @return array<string, array{int, int}> */
    public static function provideHelveticaUnicodeWidths(): array
    {
        return [
            // [ unicode_codepoint, expected_width ]
            'emdash' => [0x2014, 1000],
            'endash' => [0x2013, 556],
            'quoteleft' => [0x2018, 222],
            'quoteright' => [0x2019, 222],
            'quotedblleft' => [0x201C, 333],
            'quotedblright' => [0x201D, 333],
            'bullet' => [0x2022, 350],
            'ellipsis' => [0x2026, 1000],
            'trademark' => [0x2122, 1000],
            'Euro' => [0x20AC, 556],
            'OE' => [0x0152, 1000],
            'oe' => [0x0153, 944],
            'Scaron' => [0x0160, 667],
            'scaron' => [0x0161, 500],
            'Ydieresis' => [0x0178, 667],
            'Zcaron' => [0x017D, 611],
            'zcaron' => [0x017E, 500],
            // fi/fl are not WinAnsi-encoded but must appear in cwu
            'fi' => [0xFB01, 500],
            'fl' => [0xFB02, 500],
        ];
    }

    /**
     * @throws FileException
     * @throws FontException
     */
    #[DataProvider('provideHelveticaUnicodeWidths')]
    public function testHelveticaUnicodeWidth(int $codepoint, int $expected): void
    {
        $fdt = $this->getHelveticaMetrics();
        $this->assertArrayHasKey('cwu', $fdt);
        $this->assertSame(
            $expected,
            $fdt['cwu'][$codepoint] ?? null,
            'cwu[U+' . \strtoupper(\dechex($codepoint)) . ']',
        );
    }

    /**
     * @throws FileException
     * @throws FontException
     */
    public function testGetFontMetricsHandlesAfmWithoutCharMetrics(): void
    {
        // an AFM with zero "C" lines must not trigger a DivisionByZeroError in setCharWidths
        $afm =
            "StartFontMetrics 4.1\n"
            . "FontName TestEmpty\n"
            . "FullName Test Empty\n"
            . "FontBBox 0 -200 1000 700\n"
            . "CapHeight 700\n"
            . "Ascender 700\n"
            . "Descender -200\n"
            . "StartCharMetrics 0\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n";

        $core = new Core($afm, self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        $this->assertSame(0, $fdt['AvgWidth']);
    }

    /**
     * Build a minimal AFM, optionally with a broken or absent FontBBox row.
     */
    private function buildAfm(?string $fontBBox): string
    {
        return (
            "StartFontMetrics 4.1\n"
            . "FontName TestBBox\n"
            . "FullName Test BBox\n"
            . ($fontBBox === null ? '' : 'FontBBox ' . $fontBBox . "\n")
            . "CapHeight 700\n"
            . "StartCharMetrics 1\n"
            . "C 65 ; WX 600 ; N A ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n"
        );
    }

    /** @return array<string, array{0: string|null}> */
    public static function provideBrokenFontBBox(): array
    {
        return [
            'no FontBBox row at all' => [null],
            'empty row' => [''],
            'only two values' => ['0 -200'],
            'only three values' => ['0 -200 1000'],
        ];
    }

    /**
     * Extra trailing columns are tolerated: only the first four are meaningful.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testGetFontMetricsIgnoresExtraFontBBoxColumns(): void
    {
        $core = new Core($this->buildAfm('0 -200 1000 700 42'), self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        $this->assertSame(700, $fdt['Ascender']);
        $this->assertSame(-200, $fdt['Descender']);
    }

    /**
     * Ascender and Descender are read from FontBBox[3] and FontBBox[1], so a missing or
     * short row fails the import.
     *
     * @throws FileException
     * @throws FontException
     */
    #[DataProvider('provideBrokenFontBBox')]
    public function testGetFontMetricsRejectsAMalformedFontBBox(?string $fontBBox): void
    {
        try {
            // Core parses the AFM in its constructor, so the failure surfaces there
            $core = new Core($this->buildAfm($fontBBox), self::$fdtTemplate, new \Com\Tecnick\File\File());
            $core->getFontMetrics();
        } catch (FontException $exception) {
            $this->assertStringContainsString('FontBBox', $exception->getMessage());
            return;
        }

        $this->fail('a malformed FontBBox must be rejected');
    }

    /**
     * The bounding box spans the tallest and deepest outlines of the font, so it overstates
     * the typographic ascent and descent: the values the AFM declares win over it.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testGetFontMetricsKeepsTheAscenderAndDescenderDeclaredByTheAfm(): void
    {
        $afm =
            "StartFontMetrics 4.1\n"
            . "FontName TestAscender\n"
            . "FullName Test Ascender\n"
            . "FontBBox -166 -225 1000 931\n"
            . "Ascender 718\n"
            . "Descender -207\n"
            . "StartCharMetrics 1\n"
            . "C 65 ; WX 600 ; N A ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n";

        $core = new Core($afm, self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        $this->assertSame(718, $fdt['Ascender']);
        $this->assertSame(-207, $fdt['Descender']);
        $this->assertSame(718, $fdt['Ascent']);
        $this->assertSame(-207, $fdt['Descent']);
        // CapHeight is not declared either, and still falls back to the ascender
        $this->assertSame(718, $fdt['CapHeight']);
    }

    /**
     * The AFM spec does not require whitespace around the ';' separating the pairs of a
     * CharMetrics row, so the separator is isolated before the row is split.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testGetFontMetricsReadsCharMetricsRowsWithoutSpacesAroundTheSeparator(): void
    {
        $afm =
            "StartFontMetrics 4.1\n"
            . "FontName TestPacked\n"
            . "FullName Test Packed\n"
            . "FontBBox 0 -200 1000 700\n"
            . "StartCharMetrics 2\n"
            . "C 32;WX 600;N space;B 0 0 0 0;\n"
            . "C 65;WX 722;N A;B 10 0 700 700;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n";

        $core = new Core($afm, self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        $this->assertSame(600, $fdt['cw'][32] ?? null);
        $this->assertSame(722, $fdt['cw'][65] ?? null);
        $this->assertSame([10, 0, 700, 700], $fdt['cbbox'][65] ?? null);
    }

    /**
     * An AFM declaring neither FontName nor FullName has no name to write as /BaseFont.
     *
     * @throws FileException
     */
    public function testGetFontMetricsRejectsAnAfmWithoutAFontName(): void
    {
        $afm =
            "StartFontMetrics 4.1\n"
            . "FontBBox 0 -200 1000 700\n"
            . "StartCharMetrics 1\n"
            . "C 65 ; WX 600 ; N A ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n";

        try {
            $core = new Core($afm, self::$fdtTemplate, new \Com\Tecnick\File\File());
            $core->getFontMetrics();
        } catch (FontException $exception) {
            $this->assertStringContainsString('font name', $exception->getMessage());
            return;
        }

        $this->fail('an AFM without a font name must be rejected');
    }

    /**
     * @throws FileException
     * @throws FontException
     */
    public function testGetFontMetricsDerivesAscenderAndDescenderFromFontBBox(): void
    {
        $core = new Core($this->buildAfm('0 -200 1000 700'), self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        $this->assertSame(-200, $fdt['Descender']);
        $this->assertSame(700, $fdt['Ascender']);
        $this->assertSame(-200, $fdt['Descent']);
        $this->assertSame(700, $fdt['Ascent']);
        $this->assertSame('0 -200 1000 700', $fdt['bbox']);
    }

    /**
     * Rows for .notdef and for glyphs with no name carry no width to record.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testGetFontMetricsSkipsNamelessAndNotdefCharMetrics(): void
    {
        $afm =
            "StartFontMetrics 4.1\n"
            . "FontName TestSkip\n"
            . "FullName Test Skip\n"
            . "FontBBox 0 -200 1000 700\n"
            . "StartCharMetrics 3\n"
            . "C -1 ; WX 250 ; N .notdef ;\n"
            . "C 32 ; WX 300 ;\n"
            . "C 65 ; WX 600 ; N A ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n";

        $core = new Core($afm, self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        // only the named, non-notdef glyph contributes a width, so it is also the average
        $this->assertSame(600, $fdt['cw'][65] ?? null);
        $this->assertSame(600, $fdt['AvgWidth']);
        // 'A' is the only glyph with a Unicode width: the other two rows recorded nothing
        $this->assertSame([65 => 600], $fdt['cwu']);
    }

    /**
     * @return TFontData
     *
     * @throws FileException
     * @throws FontException
     */
    private function getHelveticaMetrics(): array
    {
        $content = \file_get_contents(self::HELVETICA_AFM);
        $this->assertIsString($content);

        $core = new Core($content, self::$fdtTemplate, new \Com\Tecnick\File\File());

        return $core->getFontMetrics();
    }

    /**
     * The AFM spec allows the ';'-separated pairs of a CharMetrics row in any order, so
     * they must be read by key and not by column position.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testGetFontMetricsReadsCharMetricsPairsByKey(): void
    {
        $afm =
            "StartFontMetrics 4.1\n"
            . "FontName TestOrder\n"
            . "FullName Test Order\n"
            . "FontBBox 0 -200 1000 700\n"
            . "StartCharMetrics 2\n"
            . "C 65 ; N A ; WX 600 ; B 10 0 590 700 ;\n"
            // a row whose last pair is not terminated by a ';' is still read
            . "C 66 ;  WX  700 ;   N   B\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n";

        $core = new Core($afm, self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        $this->assertSame(600, $fdt['cw'][65] ?? null);
        $this->assertSame(700, $fdt['cw'][66] ?? null);
        $this->assertSame([10, 0, 590, 700], $fdt['cbbox'][65] ?? null);
        // a row without a B pair carries no glyph bounding box
        $this->assertArrayNotHasKey(66, $fdt['cbbox']);
    }

    /**
     * Only the widths of the emitted single-byte range are averaged: a width recorded for
     * a code above 255 is excluded from the sum and must be excluded from the divisor too.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testAverageWidthCountsOnlyTheEmittedRange(): void
    {
        $afm =
            "StartFontMetrics 4.1\n"
            . "FontName TestAvg\n"
            . "FullName Test Avg\n"
            . "FontBBox 0 -200 1000 700\n"
            . "StartCharMetrics 2\n"
            . "C 65 ; WX 600 ; N A ;\n"
            . "C 66 ; WX 800 ; N B ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n";

        $core = new Core($afm, self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        // (600 + 800) / 2
        $this->assertSame(700, $fdt['AvgWidth']);
    }

    /**
     * FontName is the PostScript name a PDF /BaseFont expects; FullName is a
     * human-readable variant that may hold several words.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testFontNameIsPreferredOverTheFullName(): void
    {
        $afm =
            "StartFontMetrics 4.1\n"
            . "FontName Test-Bold\n"
            . "FullName Test Bold\n"
            . "FontBBox 0 -200 1000 700\n"
            . "StartCharMetrics 1\n"
            . "C 65 ; WX 600 ; N A ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n";

        $core = new Core($afm, self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        $this->assertSame('Test-Bold', $fdt['name']);
        $this->assertSame('Test Bold', $fdt['FullName']);
    }

    /**
     * Only the codes the AFM declares get a width, so Stack::isCharDefined() reports the
     * WinAnsi .notdef slots and the control codes as missing.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testOnlyTheDeclaredCodesGetAWidth(): void
    {
        $fdt = $this->getHelveticaMetrics();

        $this->assertSame(667, $fdt['cw'][0x41] ?? null, 'A is declared');
        // WinAnsi leaves these bytes undefined ('.notdef'), and so must the width map
        foreach ([0x00, 0x01, 0x1F, 0x81, 0x8D, 0x8F, 0x90, 0x9D] as $undefined) {
            $this->assertArrayNotHasKey($undefined, $fdt['cw'], 'cw[0x' . \dechex($undefined) . ']');
        }

        // the missing width is still what a consumer falls back to for them
        $this->assertSame(278, $fdt['MissingWidth'], 'the width of the space');
    }

    /**
     * The boxes are keyed by codepoint as well as by encoding byte, the way the widths are:
     * the byte of a glyph is not its codepoint (WinAnsi 146 is U+2019).
     *
     * @throws FileException
     * @throws FontException
     */
    public function testGlyphBoundingBoxesAreAlsoKeyedByCodepoint(): void
    {
        $fdt = $this->getHelveticaMetrics();

        $byByte = $fdt['cbbox'][146] ?? null;
        $this->assertIsArray($byByte, 'quoteright at the WinAnsi byte 146');
        $this->assertSame($byByte, $fdt['cbboxu'][0x2019] ?? null, 'the same box at U+2019');
        // a glyph WinAnsi does not encode is reachable by codepoint only
        $this->assertIsArray($fdt['cbboxu'][0xFB01] ?? null, 'the fi ligature');
    }

    /**
     * The italic angle is a real number, and the italic flag of the descriptor is derived
     * from it, so it is rounded rather than truncated.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testFractionalItalicAngleIsRoundedAndKeepsTheItalicFlag(): void
    {
        $afm =
            "StartFontMetrics 4.1\n"
            . "FontName TestSlanted\n"
            . "FullName Test Slanted\n"
            . "FontBBox 0 -200 1000 700\n"
            . "ItalicAngle -0.5\n"
            . "StartCharMetrics 1\n"
            . "C 65 ; WX 600 ; N A ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n";

        $core = new Core($afm, self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        $this->assertSame(-1, $fdt['ItalicAngle'], 'rounded away from zero');
        $this->assertSame(64, $fdt['Flags'] & 64, 'the italic bit of the font descriptor');
    }

    /**
     * An upright font keeps the flag off.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testNegligibleItalicAngleRoundsToZeroAndLeavesTheFlagOff(): void
    {
        $afm =
            "StartFontMetrics 4.1\n"
            . "FontName TestUpright\n"
            . "FullName Test Upright\n"
            . "FontBBox 0 -200 1000 700\n"
            . "ItalicAngle -0.4\n"
            . "StartCharMetrics 1\n"
            . "C 65 ; WX 600 ; N A ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n";

        $core = new Core($afm, self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        $this->assertSame(0, $fdt['ItalicAngle']);
        $this->assertSame(0, $fdt['Flags'] & 64);
    }

    /**
     * A 'C' group with no value declares no character code rather than the code zero, so
     * the glyph gets no width and no bounding box.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testUnencodedFontSpecificGlyphDoesNotTakeTheNotdefSlot(): void
    {
        $afm =
            "StartFontMetrics 4.1\n"
            . "FontName TestSymbolic\n"
            . "FullName Test Symbolic\n"
            . "EncodingScheme FontSpecific\n"
            . "FontBBox 0 -200 1000 700\n"
            . "StartCharMetrics 3\n"
            . "C 32 ; WX 250 ; N space ; B 0 0 0 0 ;\n"
            . "C ; WX 999 ; N beta ; B 0 0 999 700 ;\n"
            . "C -1 ; WX 998 ; N gamma ; B 0 0 998 700 ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n";

        $core = new Core($afm, self::$fdtTemplate, new \Com\Tecnick\File\File());
        $fdt = $core->getFontMetrics();

        $this->assertArrayNotHasKey(0, $fdt['cw'], 'the unencoded glyphs have no character code');
        $this->assertArrayNotHasKey(0, $fdt['cbbox']);
        $this->assertSame(250, $fdt['cw'][32] ?? null, 'the encoded glyph is still read');
    }

    /**
     * Build a minimal AFM declaring the given weight and, optionally, the stem widths.
     */
    private function buildStemAfm(string $weight, ?string $stems): string
    {
        return (
            "StartFontMetrics 4.1\n"
            . "FontName TestStem\n"
            . "FullName Test Stem\n"
            . 'Weight '
            . $weight
            . "\n"
            . "FontBBox 0 -200 1000 700\n"
            . ($stems === null ? '' : $stems)
            . "StartCharMetrics 1\n"
            . "C 65 ; WX 600 ; N A ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n"
        );
    }

    /**
     * The stem widths an AFM declares are the ones reported.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testTheDeclaredStemWidthsAreKept(): void
    {
        $core = new Core(
            $this->buildStemAfm('Medium', "StdVW 88\nStdHW 76\n"),
            self::$fdtTemplate,
            new \Com\Tecnick\File\File(),
        );
        $fdt = $core->getFontMetrics();

        $this->assertSame(88, $fdt['StemV']);
        $this->assertSame(76, $fdt['StemH']);
    }

    /**
     * An AFM is not required to declare StdVW, so the weight-derived fallback applies.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testAnAfmWithoutStemWidthsFallsBackByWeight(): void
    {
        foreach (['Medium' => 70, 'Roman' => 70, 'Bold' => 123, 'Black' => 123] as $weight => $expected) {
            $core = new Core($this->buildStemAfm($weight, null), self::$fdtTemplate, new \Com\Tecnick\File\File());
            $fdt = $core->getFontMetrics();

            $this->assertSame($expected, $fdt['StemV'], $weight);
            $this->assertSame(30, $fdt['StemH'], $weight);
        }
    }

    /**
     * The Core 14 files all declare the row, which the fallback does not displace.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testHelveticaKeepsTheStemWidthsOfItsAfm(): void
    {
        $fdt = $this->getHelveticaMetrics();

        $this->assertSame($fdt['StdVW'], $fdt['StemV']);
        $this->assertGreaterThan(0, $fdt['StemV']);
    }
}
