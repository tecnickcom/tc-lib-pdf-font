<?php
/**
 * BufferTest.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Test;

/**
 * Buffer Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class BufferTest extends \PHPUnit_Framework_TestCase
{
    protected $preserveGlobalState = false;
    protected $runTestInSeparateProcess = true;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test

        define('K_PATH_FONTS', __DIR__.'/../target/tmptest/');
        system('rm -rf '.K_PATH_FONTS.' && mkdir -p '.K_PATH_FONTS);
    }

    public function testBufferMissingKey()
    {
        $this->setExpectedException('\Com\Tecnick\Pdf\Font\Exception');
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();
        $buffer->getFont('missing');
    }

    public function testBufferMissingFontName()
    {
        $this->setExpectedException('\Com\Tecnick\Pdf\Font\Exception');
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();
        $objnum = 1;
        $buffer->add($objnum, '');
    }

    public function testBufferIFileMissing()
    {
        $this->setExpectedException('\Com\Tecnick\Pdf\Font\Exception');
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();
        $objnum = 1;
        $buffer->add($objnum, 'something', '', '/missing/nothere.json');
    }

    public function testBufferIFileNotJson()
    {
        $this->setExpectedException('\Com\Tecnick\Pdf\Font\Exception');
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();
        $objnum = 1;
        $buffer->add($objnum, 'something', '', __DIR__.'/BufferTest.php');
    }

    public function testBufferIFileWrongFormat()
    {
        $this->setExpectedException('\Com\Tecnick\Pdf\Font\Exception');
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();
        $objnum = 1;
        file_put_contents(K_PATH_FONTS.'badformat.json', '{"bad":"format"}');
        $buffer->add($objnum, 'something', '', K_PATH_FONTS.'badformat.json');
    }

    public function testLoadDeafultWidthA()
    {
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();
        $objnum = 1;
        file_put_contents(K_PATH_FONTS.'test.json', '{"type":"Type1","cw":{"0":100}}');
        $buffer->add($objnum, 'test', '', K_PATH_FONTS.'test.json');
        $font = $buffer->getFont('test');
        $this->assertEquals(600, $font['dw']);
    }

    public function testLoadDeafultWidthB()
    {
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();
        $objnum = 1;
        file_put_contents(K_PATH_FONTS.'test.json', '{"type":"Type1","cw":{"32":123}}');
        $buffer->add($objnum, 'test', '', K_PATH_FONTS.'test.json');
        $font = $buffer->getFont('test');
        $this->assertEquals(123, $font['dw']);
    }

    public function testLoadDeafultWidthC()
    {
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();
        $objnum = 1;
        file_put_contents(K_PATH_FONTS.'test.json', '{"type":"Type1","desc":{"MissingWidth":234},"cw":{"0":600}}');
        $buffer->add($objnum, 'test', '', K_PATH_FONTS.'test.json');
        $font = $buffer->getFont('test');
        $this->assertEquals(234, $font['dw']);
    }

    public function testLoadWrongType()
    {
        $this->setExpectedException('\Com\Tecnick\Pdf\Font\Exception');
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();
        $objnum = 1;
        file_put_contents(K_PATH_FONTS.'test.json', '{"type":"WRONG","cw":{"0":600}}');
        $buffer->add($objnum, 'test', '', K_PATH_FONTS.'test.json');
    }

    public function testLoadCidOnPdfa()
    {
        $this->setExpectedException('\Com\Tecnick\Pdf\Font\Exception');
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();
        $objnum = 1;
        file_put_contents(K_PATH_FONTS.'test.json', '{"type":"cidfont0","cw":{"0":600}}');
        $buffer->add($objnum, 'test', '', K_PATH_FONTS.'test.json', false, true, true);
    }

    public function testLoadArtificialStyles()
    {
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();
        $objnum = 1;
        file_put_contents(
            K_PATH_FONTS.'test.json',
            '{"type":"Core","cw":{"0":600},"mode":{"bold":true,"italic":true}}'
        );
        $buffer->add($objnum, 'symbol', '', K_PATH_FONTS.'test.json');
    }

    public function testBuffer()
    {
        $indir = __DIR__.'/../util/vendor/font/';

        $objnum = 1;
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();

        new \Com\Tecnick\Pdf\Font\Import($indir.'pdfa/pfb/PDFASymbol.pfb', null, 'Type1', 'symbol');
        $buffer->add($objnum, 'pdfasymbol');

        new \Com\Tecnick\Pdf\Font\Import($indir.'core/Helvetica.afm');
        $buffer->add($objnum, 'helvetica');
        
        new \Com\Tecnick\Pdf\Font\Import($indir.'core/Helvetica-Bold.afm');
        $buffer->add($objnum, 'helvetica', 'B');
        
        new \Com\Tecnick\Pdf\Font\Import($indir.'core/Helvetica-BoldOblique.afm');
        $buffer->add($objnum, 'helveticaBI');
        
        new \Com\Tecnick\Pdf\Font\Import($indir.'core/Helvetica-Oblique.afm');
        $buffer->add($objnum, 'helvetica', 'I');

        new \Com\Tecnick\Pdf\Font\Import($indir.'freefont/FreeSans.ttf');
        $buffer->add($objnum, 'freesans', '');
        
        new \Com\Tecnick\Pdf\Font\Import($indir.'freefont/FreeSansBold.ttf');
        $buffer->add($objnum, 'freesans', 'B');

        new \Com\Tecnick\Pdf\Font\Import($indir.'freefont/FreeSansOblique.ttf');
        $buffer->add($objnum, 'freesans', 'I');

        new \Com\Tecnick\Pdf\Font\Import($indir.'freefont/FreeSansBoldOblique.ttf');
        $buffer->add($objnum, 'freesans', 'BIUDO', '', true);

        $fontkey = $buffer->add($objnum, 'freesans', 'BI', '', true);
        $this->assertEquals('freesansBI', $fontkey);

        $this->assertEquals(10, $objnum);
        $this->assertCount(9, $buffer->getFonts());
        $this->assertCount(1, $buffer->getEncDiffs());

        $font = $buffer->getFont('freesansB');
        $this->assertNotEmpty($font);
        $this->assertEquals('FreeSansBold', $font['name']);
        $this->assertEquals('TrueTypeUnicode', $font['type']);

        $buffer->setFontSubKey('freesansBI', 'test_field', 'test_value');
        $font = $buffer->getFont('freesansBI');
        $this->assertEquals('test_value', $font['test_field']);

        $buffer->setFontSubKey('newfont', 'tfield', 'tval');
        $font = $buffer->getFont('newfont');
        $this->assertEquals('tval', $font['tfield']);

        new \Com\Tecnick\Pdf\Font\Import($indir.'pdfa/pfb/PDFAHelveticaBoldOblique.pfb');
        $buffer->add($objnum, 'arial', 'BIUDO', '', true, false, true);
        $font = $buffer->getFont('pdfahelveticaBI');
        $this->assertNotEmpty($font);

        new \Com\Tecnick\Pdf\Font\Import($indir.'core/ZapfDingbats.afm');
        $buffer->add($objnum, 'zapfdingbats', 'BIUDO');
        $font = $buffer->getFont('zapfdingbats');
        $this->assertNotEmpty($font);
    }
}
