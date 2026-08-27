<?php

/**
 * StackTest.php
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

use Com\Tecnick\File\Exception as FileException;
use Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Stack Test
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @SuppressWarnings("PHPMD.LongVariable")
 */
class StackTest extends TestUtil
{
    private function prepareTestEnvironment(): void
    {
        parent::setupTest();
    }

    /**
     * The line box of an AFM based font is widened to the FontBBox, while the font
     * descriptor keeps the values the file declares.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testCoreFontLineBoxIsMeasuredFromTheFontBBox(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'core/Helvetica.afm');
        // a size of 1000 makes the scaling ratio 1, so the metric is in font units
        $metric = $stack->insert($objnum, 'helvetica', '', 1000);

        $this->assertSame('Core', $metric['type']);
        $this->assertEqualsWithDelta(931.0, $metric['ascent'], 0.01, 'the ascent is the FontBBox top');
        $this->assertEqualsWithDelta(-225.0, $metric['descent'], 0.01, 'the descent is the FontBBox bottom');
        $this->assertEqualsWithDelta(1156.0, $metric['height'], 0.01, 'the line box spans the FontBBox');

        // the declared cap height is untouched, and it now sits below the top of the line
        // box, so a capital no longer touches it
        $this->assertEqualsWithDelta(718.0, $metric['capheight'], 0.01);
        $this->assertGreaterThan($metric['capheight'], $metric['ascent']);

        // the font descriptor keeps the values declared by the AFM file
        $desc = $stack->getFont($metric['key'])['desc'];
        $this->assertSame(718, $desc['Ascent'], 'the descriptor /Ascent stays the declared ascent');
        $this->assertSame(-207, $desc['Descent'], 'the descriptor /Descent stays the declared descent');
    }

    /**
     * A TrueType font declares its own line metrics in the 'hhea' table, which already carry
     * the internal leading, so the line box is measured from them and not from the FontBBox.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTrueTypeFontLineBoxKeepsTheDeclaredMetrics(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $metric = $stack->insert($objnum, 'freesans', '', 1000);

        $desc = $stack->getFont($metric['key'])['desc'];
        $this->assertEqualsWithDelta((float) $desc['Ascent'], $metric['ascent'], 0.01);
        $this->assertEqualsWithDelta((float) $desc['Descent'], $metric['descent'], 0.01);
        $this->assertGreaterThan(
            $metric['ascent'],
            $metric['fbbox'][3] ?? 0.0,
            'the FontBBox is the taller of the two',
        );
    }

    /**
     * Each split entry reports the width accumulated since the previous split point, the
     * first word included.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testSplitReportsTheWidthOfTheFirstWord(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'core/Helvetica.afm');
        $stack->insert($objnum, 'helvetica', '', 10, 0, 1);

        // 'ab cd': the split points are the space and the appended terminator
        $dims = $stack->getOrdArrDims([97, 98, 32, 99, 100]);
        $split = $dims['split'];
        $this->assertCount(2, $split);

        $firstWord = $split[0] ?? null;
        $secondWord = $split[1] ?? null;
        $this->assertIsArray($firstWord);
        $this->assertIsArray($secondWord);

        $first = $stack->getOrdArrDims([97, 98])['totwidth'];
        $this->bcAssertEqualsWithDelta($first, $firstWord['wordwidth'], 0.0001);
        // the second word carries the separator that opens it
        $this->bcAssertEqualsWithDelta(
            $secondWord['totwidth'] - $firstWord['totwidth'],
            $secondWord['wordwidth'],
            0.0001,
        );
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testStack(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(0.75, true, true, true);

        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $cfont = $stack->insert($objnum, 'freesans', '', 12, -0.1, 0.9, '', null);
        $this->assertNotEmpty($cfont);
        $this->assertNotEmpty($cfont['cbbox']);

        $this->bcAssertEqualsWithDelta([0.162, 0.0, 7.0308, 8.748], $stack->getCharBBox(65), 0.0001);

        new \Com\Tecnick\Pdf\Font\Import($indir . 'pdfa/pfb/PDFATimes.pfb');
        $afont = $stack->insert($objnum, 'times', '', 14, 0.3, 1.2, '', null);
        $this->assertNotEmpty($afont);

        new \Com\Tecnick\Pdf\Font\Import($indir . 'pdfa/pfb/PDFAHelveticaBoldOblique.pfb');
        $bfont = $stack->insert($objnum, 'helvetica', 'BIUDO', null, null, null, '', null);
        $this->assertNotEmpty($bfont);

        $this->assertEquals("BT /F3 14.000000 Tf ET\r", $bfont['out']);
        $this->assertEquals('pdfahelveticaBI', $bfont['key']);
        $this->assertEquals('Type1', $bfont['type']);
        $this->bcAssertEqualsWithDelta(14, $bfont['size'], 0.0001);
        $this->bcAssertEqualsWithDelta(0.3, $bfont['spacing'], 0.0001);
        $this->bcAssertEqualsWithDelta(1.2, $bfont['stretching'], 0.0001);
        $this->bcAssertEqualsWithDelta(18.6667, $bfont['usize'], 0.0001);
        $this->bcAssertEqualsWithDelta(0.014, $bfont['cratio'], 0.0001);
        $this->bcAssertEqualsWithDelta(-1.554, $bfont['up'], 0.0001);
        $this->bcAssertEqualsWithDelta(0.966, $bfont['ut'], 0.0001);
        $this->bcAssertEqualsWithDelta(4.6704, $bfont['dw'], 0.0001);
        $this->bcAssertEqualsWithDelta(13.342, $bfont['ascent'], 0.0001);
        $this->bcAssertEqualsWithDelta(-3.08, $bfont['descent'], 0.0001);
        $this->bcAssertEqualsWithDelta(16.422, $bfont['height'], 0.0001);
        $this->bcAssertEqualsWithDelta(5.131, $bfont['midpoint'], 0.0001);
        $this->bcAssertEqualsWithDelta(10.136, $bfont['capheight'], 0.0001);
        $this->bcAssertEqualsWithDelta(7.56, $bfont['xheight'], 0.0001);
        $this->bcAssertEqualsWithDelta(9.492, $bfont['avgwidth'], 0.0001);
        $this->bcAssertEqualsWithDelta(16.8, $bfont['maxwidth'], 0.0001);
        $this->bcAssertEqualsWithDelta(4.6704, $bfont['missingwidth'], 0.0001);
        $this->bcAssertEqualsWithDelta([-1.092, -3.08, 18.5976, 13.342], $bfont['fbbox'], 0.0001);

        $fkey = $stack->getCurrentFontKey();
        $this->assertEquals('pdfahelveticaBI', $fkey);

        $font = $stack->getCurrentFont();
        $this->assertEquals($bfont, $font);

        $this->assertTrue($stack->isCharDefined(65));
        $this->assertFalse($stack->isCharDefined(300));

        $this->assertEquals(75, $stack->replaceChar(65, 75));
        $this->assertEquals(65, $stack->replaceChar(65, 300));

        $this->assertEquals([0, 0, 0, 0], $stack->getCharBBox(300));

        $this->bcAssertEqualsWithDelta(12.1296, $stack->getCharWidth(65), 0.0001);
        $this->bcAssertEqualsWithDelta(0, $stack->getCharWidth(173), 0.0001);
        $this->bcAssertEqualsWithDelta(4.6704, $stack->getCharWidth(300), 0.0001);

        $uniarr = [65, 173, 300];
        $this->bcAssertEqualsWithDelta(17.52, $stack->getOrdArrWidth($uniarr), 0.0001);

        $subs = [
            65 => [400, 75],
            173 => [76, 300],
            300 => [400, 77],
        ];
        $this->assertEquals([65, 173, 77], $stack->replaceMissingChars($uniarr, $subs));

        $font = $stack->popLastFont();
        $this->assertEquals($bfont, $font);

        $font = $stack->getCurrentFont();
        $this->assertEquals($afont, $font);

        $fkey = $stack->getCurrentFontKey();
        $this->assertEquals('pdfatimes', $fkey);

        $type = $stack->getCurrentFontType();
        $this->assertEquals('Type1', $type);

        $ftype = $stack->isCurrentUnicodeFont();
        $this->assertFalse($ftype);

        $ftype = $stack->isCurrentByteFont();
        $this->assertTrue($ftype);

        $uniarr = [65, 173, 300, 32, 65, 173, 300, 32, 65, 173, 300];
        $widths = $stack->getOrdArrDims($uniarr);
        $this->assertEquals(11, $widths['chars']);
        $this->assertEquals(2, $widths['spaces']);
        $this->bcAssertEqualsWithDelta(60.9384, $widths['totwidth'], 0.0001);
        $this->bcAssertEqualsWithDelta(8.76, $widths['totspacewidth'], 0.0001);
        $this->assertEquals(6, $widths['words']);

        $split = $widths['split'][5] ?? null;
        $this->assertIsArray($split);
        $this->assertEquals(11, $split['pos']);
        $this->assertEquals(8203, $split['ord']);
        $this->assertEquals('BN', $split['septype']);
        $this->bcAssertEqualsWithDelta(4.92, $split['wordwidth'], 0.0001);
        $this->assertEquals(2, $split['spaces']);
        $this->bcAssertEqualsWithDelta(60.9384, $split['totwidth'], 0.0001);
        $this->bcAssertEqualsWithDelta(8.76, $split['totspacewidth'], 0.0001);

        $outfont = $stack->getOutCurrentFont();
        $this->assertEquals("BT /F2 14.000000 Tf ET\r", $outfont);

        $font = $stack->cloneFont($objnum, null, null, 13, 0.3, 0.7);
        $this->assertEquals(13, $font['size']);
        $this->assertEquals(0.3, $font['spacing']);
        $this->assertEquals(0.7, $font['stretching']);

        $font = $stack->cloneFont($objnum, 0, 'BI', 17, 0.7, 1.3);
        $this->assertEquals('BI', $font['style']);
        $this->assertEquals(17, $font['size']);
        $this->assertEquals(0.7, $font['spacing']);
        $this->assertEquals(1.3, $font['stretching']);

        $fname = $stack->getFontFamilyName('unknown');
        $this->assertEquals('freesansBI', $fname);

        new \Com\Tecnick\Pdf\Font\Import($indir . 'pdfa/pfb/PDFACourier.pfb');
        $bfont = $stack->insert($objnum, 'courier', '', null, null, null, '', null);
        $this->assertNotEmpty($bfont);

        $fname = $stack->getFontFamilyName('freesans');
        $this->assertEquals('freesans', $fname);

        $fname = $stack->getFontFamilyName('cursive');
        $this->assertEquals('pdfatimes', $fname);

        $fname = $stack->getFontFamilyName('unknown');
        $this->assertEquals('pdfacourier', $fname);
    }

    /** @throws FontException */
    public function testEmptyStack(): void
    {
        $this->bcExpectException(\Com\Tecnick\Pdf\Font\Exception::class);
        $this->prepareTestEnvironment();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $stack->popLastFont();
    }

    /** @throws FontException */
    public function testStackMissingFont(): void
    {
        $this->bcExpectException(\Com\Tecnick\Pdf\Font\Exception::class);
        $this->prepareTestEnvironment();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $objnum = 1;
        $stack->insert($objnum, 'missing');
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testHasCurrentFont(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(0.75, true, true, true);
        $this->assertFalse($stack->hasCurrentFont());
        $this->assertSame(0, $stack->getStackSize());
        $this->assertSame(-1, $stack->getCurrentFontIndex());

        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $font = $stack->insert($objnum, 'freesans', '', 12);
        $this->assertTrue($stack->hasCurrentFont());
        $this->assertSame(1, $stack->getStackSize());
        $this->assertSame(0, $stack->getCurrentFontIndex());
        $this->assertSame($font['out'], $stack->getOutCurrentFont());

        $stack->cloneFont($objnum, null, null, 13);
        $this->assertSame(2, $stack->getStackSize());
        $this->assertSame(1, $stack->getCurrentFontIndex());

        $stack->popLastFont();
        $this->assertTrue($stack->hasCurrentFont());
        $this->assertSame(1, $stack->getStackSize());
        $this->assertSame(0, $stack->getCurrentFontIndex());

        $stack->popLastFont();
        $this->assertFalse($stack->hasCurrentFont());
        $this->assertSame(0, $stack->getStackSize());
        $this->assertSame(-1, $stack->getCurrentFontIndex());
    }

    /**
     * Only the paragraph and segment separators, the whitespace and the boundary neutrals
     * split words.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testWordSplitOnSeparatorsOnly(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';
        $objnum = 1;

        $stack = new \Com\Tecnick\Pdf\Font\Stack(0.75, true, true, true);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $stack->insert($objnum, 'freesans', '', 12, 0, 1, '', null);

        // LATIN A, CJK UNIFIED IDEOGRAPH-4E00, HANGUL SYLLABLE GA and DEVANAGARI KA are
        // all of bidirectional type L: one word, closed by the trailing ZWSP.
        $widths = $stack->getOrdArrDims([0x41, 0x4E00, 0xAC00, 0x0915]);
        $this->assertEquals(4, $widths['chars']);
        $this->assertEquals(0, $widths['spaces']);
        $this->assertEquals(1, $widths['words']);

        // The same letters around a space: two words plus the trailing ZWSP.
        $widths = $stack->getOrdArrDims([0x41, 0x4E00, 0x20, 0xAC00, 0x0915]);
        $this->assertEquals(1, $widths['spaces']);
        $this->assertEquals(2, $widths['words']);
        $split = $widths['split'][0] ?? null;
        $this->assertIsArray($split);
        $this->assertEquals('WS', $split['septype']);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testUnicodeOrdAddedToSubsetChars(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';
        $objnum = 1;

        $stack = new \Com\Tecnick\Pdf\Font\Stack(0.75, true, true, true);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $stack->insert($objnum, 'freesans', '', 12, 0, 1, '', true);

        // Use pi and almost-equal to ensure non-latin BMP code points are tracked.
        $stack->getOrdArrDims([960, 8776]);

        $fonts = $stack->getFonts();
        $fkey = $stack->getCurrentFontKey();
        $currentFont = $fonts[$fkey] ?? null;
        $this->assertIsArray($currentFont);
        $this->assertArrayHasKey(960, $currentFont['subsetchars']);
        $this->assertArrayHasKey(8776, $currentFont['subsetchars']);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testFractionalFontSize(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(0.75, true, true, true);

        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $font = $stack->insert($objnum, 'freesans', '', 10.5);

        $this->bcAssertEqualsWithDelta(10.5, $font['size'], 0.0001);
        $this->assertEquals("BT /F1 10.500000 Tf ET\r", $font['out']);

        $clone = $stack->cloneFont($objnum, null, null, 11.25);

        $this->bcAssertEqualsWithDelta(11.25, $clone['size'], 0.0001);
        $this->assertEquals("BT /F1 11.250000 Tf ET\r", $clone['out']);
    }

    /**
     * Cloning a font with a different style loads the definition file of the requested
     * style rather than reusing the one of the source font.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testCloneFontLoadsStyledDefinitionFile(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';
        $objnum = 1;

        $stack = new \Com\Tecnick\Pdf\Font\Stack(0.75, true, true, true);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSansBold.ttf');
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSansBoldOblique.ttf');

        $regular = $stack->insert($objnum, 'freesans', '', 12);
        $this->assertEquals('freesans', $regular['key']);

        // the bold style has not been loaded yet: the clone loads it from the bold
        // definition file found in the same directory of the source font
        $clone = $stack->cloneFont($objnum, null, 'B', 12);
        $this->assertEquals('freesansB', $clone['key']);
        $this->assertEquals('B', $clone['style']);

        $font = $stack->getFont('freesansB');
        $this->assertEquals('FreeSansBold', $font['name']);
        $this->assertEquals('freesansb.json', \basename($font['ifile']));
        $this->assertFalse($font['fakestyle']);

        // the real bold glyphs are wider than the regular ones
        $regularWidth = $regular['cw'][65] ?? 0.0;
        $cloneWidth = $clone['cw'][65] ?? 0.0;
        $this->assertGreaterThan(0.0, $regularWidth);
        $this->assertGreaterThan($regularWidth, $cloneWidth);

        // decoration-only style letters are ignored when resolving the file
        $clone = $stack->cloneFont($objnum, 0, 'BIUDO', 14);
        $this->assertEquals('freesansBI', $clone['key']);
        $this->assertEquals('BIUDO', $clone['style']);

        $font = $stack->getFont('freesansBI');
        $this->assertEquals('FreeSansBoldOblique', $font['name']);
        $this->assertEquals('freesansbi.json', \basename($font['ifile']));
        $this->assertFalse($font['fakestyle']);
    }

    /**
     * Cloning a font with a different style whose definition file does not exist falls
     * back to the autodetection with the artificial style emulation.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testCloneFontFallsBackToArtificialStyleWhenStyledFileIsMissing(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';
        $objnum = 1;

        $stack = new \Com\Tecnick\Pdf\Font\Stack(0.75, true, true, true);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');

        $regular = $stack->insert($objnum, 'freesans', '', 12);
        $clone = $stack->cloneFont($objnum, null, 'B', 12);
        $this->assertEquals('freesansB', $clone['key']);

        $font = $stack->getFont('freesansB');
        $this->assertEquals('freesans.json', \basename($font['ifile']));
        $this->assertTrue($font['fakestyle']);
        $this->assertTrue($font['mode']['bold']);

        // artificial bold reuses the regular glyph widths
        $regularWidth = $regular['cw'][65] ?? 0.0;
        $cloneWidth = $clone['cw'][65] ?? 0.0;
        $this->assertGreaterThan(0.0, $regularWidth);
        $this->bcAssertEqualsWithDelta($regularWidth, $cloneWidth, 0.0001);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testCloneFontRejectsOutOfRangeIndex(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';
        $objnum = 1;

        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'core/Helvetica.afm');
        $stack->insert($objnum, 'helvetica');

        $this->bcExpectException(\Com\Tecnick\Pdf\Font\Exception::class);
        $stack->cloneFont($objnum, 99);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testReplaceMissingCharsKeepsOriginalWhenNoSubstitutesProvided(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';
        $objnum = 1;

        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'core/Helvetica.afm');
        $stack->insert($objnum, 'helvetica');

        $this->assertSame([400], $stack->replaceMissingChars([400], []));
    }

    /** @throws FontException */
    public function testGetFontFamilyNameRejectsEmptyString(): void
    {
        $this->prepareTestEnvironment();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);

        $this->bcExpectException(\Com\Tecnick\Pdf\Font\Exception::class);
        $stack->getFontFamilyName('');
    }

    /** @throws FontException */
    public function testGetCharWidthFailsWithoutCurrentFont(): void
    {
        $this->prepareTestEnvironment();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);

        $this->bcExpectException(\Com\Tecnick\Pdf\Font\Exception::class);
        $stack->getCharWidth(65);
    }

    /** @throws FontException */
    public function testMalformedCharBoxDataIsIgnored(): void
    {
        $this->prepareTestEnvironment();
        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);

        \file_put_contents(
            $this->getFontPath() . 'badbbox.json',
            '{"type":"Type1","desc":{"FontBBox":"[0 0 0 0]"},"cw":{"65":400},"cbbox":{"65":[1,2,3]}}',
        );

        $stack->insert($objnum, 'badbbox', '', null, null, null, $this->getFontPath() . 'badbbox.json', null);
        $this->assertSame([0.0, 0.0, 0.0, 0.0], $stack->getCharBBox(65));
    }

    /**
     * The metric scales the four corners of every box it is given, and skips a box that
     * does not hold four.
     *
     * @throws FontException
     */
    public function testAGlyphBoxWithoutFourCornersIsNotScaled(): void
    {
        $harness = new StackTestHarness(1);

        $scaled = $harness->runScaleBBoxMap(
            [
                65 => [1, 2, 3],
                66 => [1, 2, 3, 4],
            ],
            2.0,
            3.0,
        );

        $this->assertSame([66 => [2.0, 6.0, 6.0, 12.0]], $scaled);
    }

    // -------------------------------------------------------------------------
    // font metric cache
    // -------------------------------------------------------------------------

    /**
     * The cached metric is shared between stack entries and its index is patched on the way
     * out, so pushing the same font twice yields a metric pointing at the current slot.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testSameFontPushedTwiceGetsItsOwnStackIndex(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');

        // identical font, size, spacing, stretching and style on both pushes
        $first = $stack->insert($objnum, 'freesans', '', 12, 0.0, 1.0, '', null);
        $second = $stack->insert($objnum, 'freesans', '', 12, 0.0, 1.0, '', null);

        $this->assertSame(0, $first['idx']);
        $this->assertSame(1, $second['idx']);
        $this->assertSame(1, $stack->getCurrentFontIndex());
        // everything else about the two metrics is identical
        $this->assertSame($first['key'], $second['key']);
        $this->assertSame($first['size'], $second['size']);

        // popLastFont returns the metric of the font being removed
        $popped = $stack->popLastFont();
        $this->assertSame(1, $popped['idx']);
        $this->assertSame(0, $stack->getCurrentFont()['idx']);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testMetricCacheStillReusesEntriesForTheSameStackSlot(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $stack->insert($objnum, 'freesans', '', 12, 0.0, 1.0, '', null);

        // repeated reads of the same slot are served from the cache
        $this->assertSame($stack->getCurrentFont(), $stack->getCurrentFont());

        $prop = new \ReflectionProperty($stack, 'metric');
        /** @var array<string, mixed> $metric */
        $metric = $prop->getValue($stack);
        $this->assertCount(1, $metric);
    }

    // -------------------------------------------------------------------------
    // character spacing on a leading separator
    // -------------------------------------------------------------------------

    /**
     * The spacing term of a separator is clamped at zero, so a separator at index 0
     * contributes no negative width.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testLeadingSeparatorDoesNotProduceANegativeWidth(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        // non-zero character spacing is what makes the term visible
        $stack->insert($objnum, 'freesans', '', 12, 2.0, 1.0, '', null);

        // a leading space, then 'AB'
        $dims = $stack->getOrdArrDims([0x20, 0x41, 0x42]);

        /** @var array<int, array<string, float|int>> $split */
        $split = $dims['split'];
        $this->assertNotEmpty($split);

        $firstWord = \reset($split);
        $this->assertIsArray($firstWord);
        $this->assertSame(0, $firstWord['pos'] ?? null);
        // the running total at index 0 is the width accumulated so far: zero, never negative
        $this->assertSame(0.0, (float) ($firstWord['totwidth'] ?? -1));

        foreach ($split as $word) {
            $this->assertGreaterThanOrEqual(0.0, (float) ($word['totwidth'] ?? -1));
            $this->assertGreaterThanOrEqual(0.0, (float) ($word['wordwidth'] ?? -1));
        }
    }

    /**
     * A separator that is not first keeps the spacing term it accumulated.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testSeparatorAfterTheFirstCharacterKeepsItsSpacingTerm(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $stack->insert($objnum, 'freesans', '', 12, 2.0, 1.0, '', null);

        // 'A', space, 'B': the separator sits at index 1, so the clamp does not apply
        $dims = $stack->getOrdArrDims([0x41, 0x20, 0x42]);

        /** @var array<int, array<string, float|int>> $split */
        $split = $dims['split'];
        $firstWord = \reset($split);
        $this->assertIsArray($firstWord);
        $this->assertSame(1, $firstWord['pos'] ?? null);
        // width of 'A' plus one spacing unit
        $this->assertGreaterThan(2.0, (float) ($firstWord['totwidth'] ?? 0));
    }

    /**
     * getOrdArrDims() appends a ZWSP internally to close the last word, and that terminator
     * does not enter the subset of the font.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTextDimensionsDoNotAddTheInternalTerminatorToTheSubset(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1, true, true, false);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $stack->insert($objnum, 'freesans', '', 12, 0, 1, '', true);

        $stack->getOrdArrDims([0x41, 0x42]);

        $fonts = $stack->getFonts();
        $subsetchars = $fonts['freesans']['subsetchars'] ?? [];

        $this->assertArrayHasKey(0x41, $subsetchars);
        $this->assertArrayHasKey(0x42, $subsetchars);
        // 8203 = ZWSP, the internal terminator
        $this->assertArrayNotHasKey(8203, $subsetchars);
    }

    /**
     * getOrdArrDims() reads the key of each entry as its position in the string, so the
     * input is normalised to a list first.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTextDimensionsIgnoreTheKeysOfTheCodepointArray(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'core/Helvetica.afm');
        // a non-zero spacing makes the position of each codepoint observable
        $stack->insert($objnum, 'helvetica', '', 10, 2, 1);

        $list = $stack->getOrdArrDims([65, 66, 67]);
        $gappy = $stack->getOrdArrDims([0 => 65, 2 => 66, 5 => 67]);

        $this->assertSame($list['totwidth'], $gappy['totwidth']);
        $this->assertSame($list['chars'], $gappy['chars']);
        $this->assertSame($list['words'], $gappy['words']);
        $this->assertSame($list['split'], $gappy['split']);
    }

    /**
     * The terminator is addressed by count(), which on a gapped input would fall on a real
     * codepoint, so the keys are made consecutive before the loop runs.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTextDimensionsSubsetEveryCodepointOfAGappedArray(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1, true, true, false);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $stack->insert($objnum, 'freesans', '', 12, 0, 1, '', true);

        // count() is 2, which is also the key of the second codepoint
        $stack->getOrdArrDims([0 => 0x41, 2 => 0x42]);

        $fonts = $stack->getFonts();
        $subsetchars = $fonts['freesans']['subsetchars'] ?? [];

        $this->assertArrayHasKey(0x41, $subsetchars);
        $this->assertArrayHasKey(0x42, $subsetchars);
        $this->assertArrayNotHasKey(8203, $subsetchars);
    }

    /**
     * The metric cache is shared between the stack entries that resolve to the same font,
     * and each lookup reports the index it was asked for.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testFontMetricReportsTheRequestedStackIndex(): void
    {
        $this->prepareTestEnvironment();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $first = $stack->insert($objnum, 'freesans', '', 12, 0, 1, '', false);
        $second = $stack->insert($objnum, 'freesans', '', 12, 0, 1, '', false);

        $this->assertSame(0, $first['idx']);
        $this->assertSame(1, $second['idx']);
        // the scaled widths are shared, only the index differs
        $this->assertSame($first['cw'], $second['cw']);
        $current = $stack->getCurrentFont();
        $this->assertSame(1, $current['idx']);

        $popped = $stack->popLastFont();
        $this->assertSame(1, $popped['idx']);
        $restored = $stack->getCurrentFont();
        $this->assertSame(0, $restored['idx']);
    }
}
