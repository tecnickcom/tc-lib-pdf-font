<?php

/**
 * BufferTest.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Buffer Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class BufferTest extends TestUtil
{
    public function testStackMissingKey()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Font\Exception');
        $this->setupTest();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $stack->getFont('missing');
    }

    public function testStackMissingFontName()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Font\Exception');
        $this->setupTest();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $objnum = 1;
        $stack->add($objnum, '');
    }

    public function testStackIFileMissing()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Font\Exception');
        $this->setupTest();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $objnum = 1;
        $stack->add($objnum, 'something', '', '/missing/nothere.json');
    }

    public function testStackIFileNotJson()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Font\Exception');
        $this->setupTest();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $objnum = 1;
        $stack->add($objnum, 'something', '', __DIR__ . '/StackTest.php');
    }

    public function testStackIFileWrongFormat()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Font\Exception');
        $this->setupTest();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $objnum = 1;
        file_put_contents($this->getFontPath() . 'badformat.json', '{"bad":"format"}');
        $stack->add($objnum, 'something', '', $this->getFontPath() . 'badformat.json');
    }

    public function testLoadDeafultWidthA()
    {
        $this->setupTest();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $objnum = 1;
        file_put_contents($this->getFontPath() . 'test.json', '{"type":"Type1","cw":{"0":100}}');
        $stack->add($objnum, 'test', '', $this->getFontPath() . 'test.json');
        $font = $stack->getFont('test');
        $this->assertEquals(600, $font['dw']);
    }

    public function testLoadDeafultWidthB()
    {
        $this->setupTest();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $objnum = 1;
        file_put_contents($this->getFontPath() . 'test.json', '{"type":"Type1","cw":{"32":123}}');
        $stack->add($objnum, 'test', '', $this->getFontPath() . 'test.json');
        $font = $stack->getFont('test');
        $this->assertEquals(123, $font['dw']);
    }

    public function testLoadDeafultWidthC()
    {
        $this->setupTest();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $objnum = 1;
        file_put_contents(
            $this->getFontPath() . 'test.json',
            '{"type":"Type1","desc":{"MissingWidth":234},"cw":{"0":600}}'
        );
        $stack->add($objnum, 'test', '', $this->getFontPath() . 'test.json');
        $font = $stack->getFont('test');
        $this->assertEquals(234, $font['dw']);
    }

    public function testLoadWrongType()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Font\Exception');
        $this->setupTest();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $objnum = 1;
        file_put_contents($this->getFontPath() . 'test.json', '{"type":"WRONG","cw":{"0":600}}');
        $stack->add($objnum, 'test', '', $this->getFontPath() . 'test.json');
    }

    public function testLoadCidOnPdfa()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Font\Exception');
        $this->setupTest();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1, false, true, true);
        $objnum = 1;
        file_put_contents($this->getFontPath() . 'test.json', '{"type":"cidfont0","cw":{"0":600}}');
        $stack->add($objnum, 'test', '', $this->getFontPath() . 'test.json', false);
    }

    public function testLoadArtificialStyles()
    {
        $this->setupTest();
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $objnum = 1;
        file_put_contents(
            $this->getFontPath() . 'test.json',
            '{"type":"Core","cw":{"0":600},"mode":{"bold":true,"italic":true}}'
        );
        $key = $stack->add($objnum, 'symbol', '', $this->getFontPath() . 'test.json');
        $this->assertNotEmpty($key);
    }

    public function testBuffer()
    {
        $this->setupTest();
        $indir = dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1, false, true, false);

        new \Com\Tecnick\Pdf\Font\Import($indir . 'pdfa/pfb/PDFASymbol.pfb', '', 'Type1', 'symbol');
        $stack->add($objnum, 'pdfasymbol');

        new \Com\Tecnick\Pdf\Font\Import($indir . 'core/Helvetica.afm');
        $stack->add($objnum, 'helvetica');

        new \Com\Tecnick\Pdf\Font\Import($indir . 'core/Helvetica-Bold.afm');
        $stack->add($objnum, 'helvetica', 'B');

        new \Com\Tecnick\Pdf\Font\Import($indir . 'core/Helvetica-BoldOblique.afm');
        $stack->add($objnum, 'helveticaBI');

        new \Com\Tecnick\Pdf\Font\Import($indir . 'core/Helvetica-Oblique.afm');
        $stack->add($objnum, 'helvetica', 'I');

        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSans.ttf');
        $stack->add($objnum, 'freesans', '');

        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSansBold.ttf');
        $stack->add($objnum, 'freesans', 'B');

        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSansOblique.ttf');
        $stack->add($objnum, 'freesans', 'I');

        new \Com\Tecnick\Pdf\Font\Import($indir . 'freefont/FreeSansBoldOblique.ttf');
        $stack->add($objnum, 'freesans', 'BIUDO', '', true);

        $fontkey = $stack->add($objnum, 'freesans', 'BI', '', true);
        $this->assertEquals('freesansBI', $fontkey);

        $this->assertEquals(10, $objnum);
        $this->assertCount(9, $stack->getFonts());
        $this->assertCount(1, $stack->getEncDiffs());

        $font = $stack->getFont('freesansB');
        $this->assertNotEmpty($font);
        $this->assertEquals('FreeSansBold', $font['name']);
        $this->assertEquals('TrueTypeUnicode', $font['type']);

        $stack->setFontSubKey('freesansBI', 'test_field', 'test_value');
        $font = $stack->getFont('freesansBI');
        $this->assertEquals('test_value', $font['test_field']);

        $stack->setFontSubKey('newfont', 'tfield', 'tval');
        $font = $stack->getFont('newfont');
        $this->assertEquals('tval', $font['tfield']);

        new \Com\Tecnick\Pdf\Font\Import($indir . 'core/ZapfDingbats.afm');
        $stack->add($objnum, 'zapfdingbats', 'BIUDO');
        $font = $stack->getFont('zapfdingbats');
        $this->assertNotEmpty($font);
    }

    public function testBufferPdfa()
    {
        $this->setupTest();
        $indir = dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1, true, false, true);

        new \Com\Tecnick\Pdf\Font\Import($indir . 'pdfa/pfb/PDFAHelveticaBoldOblique.pfb');
        $stack->add($objnum, 'arial', 'BIUDO', '', true);
        $font = $stack->getFont('pdfahelveticaBI');
        $this->assertNotEmpty($font);
    }
}
