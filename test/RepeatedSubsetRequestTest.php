<?php

/**
 * RepeatedSubsetRequestTest.php
 *
 * @since     2026-08-27
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
use Com\Tecnick\Pdf\Font\Stack;

/**
 * The subsetting mode of a font requested more than once.
 *
 * A font is subset only when every request for it asked for a subset, whichever request
 * comes first.
 *
 * @since     2026-08-27
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class RepeatedSubsetRequestTest extends TestUtil
{
    /**
     * Write the definition file of a font whose type can be subset.
     */
    private function writeFont(): void
    {
        \file_put_contents(
            $this->getFontPath() . 'reduced.json',
            '{"type":"TrueTypeUnicode","name":"reduced","dw":600,"cw":{"65":400}'
            . ',"desc":{"FontBBox":"[0 -200 1000 900]","Ascent":900,"Descent":-200}}',
        );
    }

    /**
     * Returns the subsetting mode the buffer holds for the font.
     *
     * @throws FontException
     */
    private function subsetOf(Stack $stack): bool
    {
        return $stack->getFont('reduced')['subset'];
    }

    /**
     * @throws \Throwable
     */
    public function testARequestForTheWholeProgramWinsOverAnEarlierSubsetOne(): void
    {
        $this->setupTest();
        $this->writeFont();

        $stack = new Stack(1);
        $objnum = 1;
        $stack->insert($objnum, 'reduced', '', 10, null, null, '', true);
        $this->assertTrue($this->subsetOf($stack));

        $stack->insert($objnum, 'reduced', '', 12, null, null, '', false);
        $this->assertFalse($this->subsetOf($stack));
    }

    /**
     * @throws \Throwable
     */
    public function testARequestForASubsetDoesNotReduceAFontAlreadyAskedForWhole(): void
    {
        $this->setupTest();
        $this->writeFont();

        $stack = new Stack(1);
        $objnum = 1;
        $stack->insert($objnum, 'reduced', '', 10, null, null, '', false);
        $stack->insert($objnum, 'reduced', '', 12, null, null, '', true);

        $this->assertFalse($this->subsetOf($stack));
    }

    /**
     * Every request asking for a subset leaves the font reduced, which is the mode of a
     * document that states it once and never contradicts it.
     *
     * @throws \Throwable
     */
    public function testAFontStaysReducedWhileEveryRequestAsksForASubset(): void
    {
        $this->setupTest();
        $this->writeFont();

        $stack = new Stack(1, true);
        $objnum = 1;
        $stack->insert($objnum, 'reduced', '', 10);
        $stack->insert($objnum, 'reduced', '', 12);
        // the mode of the buffer applies to a request that states none
        $stack->insert($objnum, 'reduced', '', 14, null, null, '', true);

        $this->assertTrue($this->subsetOf($stack));
    }

    /**
     * The same font reached through the definition file rather than through the
     * autodetected name is the same entry of the buffer, so the modes aggregate there too.
     *
     * @throws \Throwable
     */
    public function testTheModesAggregateWhenTheDefinitionFileIsNamed(): void
    {
        $this->setupTest();
        $this->writeFont();

        $stack = new Stack(1);
        $objnum = 1;
        $stack->insert($objnum, 'reduced', '', 10, null, null, '', true);
        $stack->insert($objnum, 'reduced', '', 12, null, null, $this->getFontPath() . 'reduced.json', false);

        $this->assertFalse($this->subsetOf($stack));
    }
}
