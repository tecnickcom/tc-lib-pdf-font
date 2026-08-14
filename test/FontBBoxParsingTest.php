<?php

/**
 * FontBBoxParsingTest.php
 *
 * @since     2026-08-14
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

use Com\Tecnick\Pdf\Font\Stack;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Reading the font bounding box of a definition file.
 *
 * The four values are written as a PDF array literal, which the library emits as '[a b c d]'
 * but which a hand written or third party definition file may space differently, so any run
 * of whitespace separates them and the brackets are optional.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FontBBoxParsingTest extends TestUtil
{
    /** @return array<string, array{0: string}> */
    public static function provideFontBBoxSpelling(): array
    {
        return [
            'as this library writes it' => ['[-100 -200 1000 900]'],
            'with inner padding' => ['[ -100 -200 1000 900 ]'],
            'with several spaces' => ['[-100   -200  1000    900]'],
            'without brackets' => ['-100 -200 1000 900'],
            'with a tab' => ["[-100\t-200 1000 900]"],
        ];
    }

    /**
     * @throws \Throwable
     */
    #[DataProvider('provideFontBBoxSpelling')]
    public function testFontBBoxIsReadWhateverTheSpacing(string $fontbbox): void
    {
        $this->setupTest();
        \file_put_contents(
            $this->getFontPath() . 'boxfont.json',
            '{"type":"Core","name":"boxfont","dw":600,"cw":{"65":400}'
            . ',"desc":{"FontBBox":"'
            . \str_replace("\t", '\t', $fontbbox)
            . '","Ascent":900,"Descent":-200}}',
        );

        $objnum = 1;
        $stack = new Stack(1);
        // the size is 1000 so that the scaled box is the declared one
        $metric = $stack->insert($objnum, 'boxfont', '', 1000, 0, 1);

        $this->bcAssertEqualsWithDelta([-100.0, -200.0, 1000.0, 900.0], $metric['fbbox'], 0.0001);
    }

    /**
     * A box that does not hold four numbers is not guessed at: the missing corners are
     * zero, and a non numeric one does not become one either.
     *
     * @throws \Throwable
     */
    public function testIncompleteFontBBoxFallsBackToZeroedCorners(): void
    {
        $this->setupTest();
        \file_put_contents(
            $this->getFontPath() . 'shortbox.json',
            '{"type":"Core","name":"shortbox","dw":600,"cw":{"65":400}'
            . ',"desc":{"FontBBox":"[-100 xyz]","Ascent":900,"Descent":-200}}',
        );

        $objnum = 1;
        $stack = new Stack(1);
        $metric = $stack->insert($objnum, 'shortbox', '', 1000, 0, 1);

        $this->bcAssertEqualsWithDelta([-100.0, 0.0, 0.0, 0.0], $metric['fbbox'], 0.0001);
    }
}
