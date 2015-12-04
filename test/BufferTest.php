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
 */
class BufferTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test

        if (!defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', __DIR__.'/../target/tmptest/');
        }
    }

    public function testBuffer()
    {
        system('rm -rf '.K_PATH_FONTS.' && mkdir -p '.K_PATH_FONTS);
        $indir = __DIR__.'/../util/vendor/font/';

        $objnum = 1;
        $buffer = new \Com\Tecnick\Pdf\Font\Buffer();

        new \Com\Tecnick\Pdf\Font\Import($indir.'core/Helvetica.afm');
        $buffer->add($objnum, 'helvetica');
        
        new \Com\Tecnick\Pdf\Font\Import($indir.'core/Helvetica-Bold.afm');
        $buffer->add($objnum, 'helvetica', 'B');
        
        new \Com\Tecnick\Pdf\Font\Import($indir.'core/Helvetica-BoldOblique.afm');
        $buffer->add($objnum, 'helvetica', 'BI');
        
        new \Com\Tecnick\Pdf\Font\Import($indir.'core/Helvetica-Oblique.afm');
        $buffer->add($objnum, 'helvetica', 'I');

        new \Com\Tecnick\Pdf\Font\Import($indir.'freefont/FreeSans.ttf');
        $buffer->add($objnum, 'freesans', '');
        
        new \Com\Tecnick\Pdf\Font\Import($indir.'freefont/FreeSansBold.ttf');
        $buffer->add($objnum, 'freesans', 'B');

        new \Com\Tecnick\Pdf\Font\Import($indir.'freefont/FreeSansOblique.ttf');
        $buffer->add($objnum, 'freesans', 'I');

        new \Com\Tecnick\Pdf\Font\Import($indir.'freefont/FreeSansBoldOblique.ttf');
        $buffer->add($objnum, 'freesans', 'BI');

        
        $this->assertTrue(true);
  
    }
}
