<?php

/**
 * WinAnsiDuplicateCodesTest.php
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

use Com\Tecnick\File\Exception as FileException;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * WinAnsiEncoding assigns two bytes to the same glyph name, and both carry its metrics.
 *
 * ISO 32000-1 Annex D.2 gives 'space' the codes 32 and 160 and 'hyphen' the codes 45 and
 * 173, and both codes of each pair carry the width of the glyph.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class WinAnsiDuplicateCodesTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/';

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    private function coreStack(string $file, string $family): Stack
    {
        $this->setupTest();
        new Import(\dirname(__DIR__) . self::MIRROR . $file);

        $objnum = 1;
        $stack = new Stack(1);
        $stack->insert($objnum, $family, '', 10, 0, 1);

        return $stack;
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTheDuplicatedCodesCarryTheMetricsOfTheirGlyph(): void
    {
        $stack = $this->coreStack('core/Helvetica.afm', 'helvetica');
        $font = $stack->getFont($stack->getCurrentFontKey());

        $space = $font['cw'][32] ?? null;
        $hyphen = $font['cw'][45] ?? null;
        $this->assertNotNull($space);
        $this->assertNotNull($hyphen);

        $this->assertSame($space, $font['cw'][160] ?? null, 'the no-break space is a space');
        $this->assertSame($hyphen, $font['cw'][173] ?? null, 'the soft hyphen is a hyphen');
        // the two glyphs do not have the same width, so the assertions above are not vacuous
        $this->assertNotSame($space, $hyphen);

        $this->assertSame($font['cbbox'][45] ?? null, $font['cbbox'][173] ?? null, 'and the same box');
    }

    /**
     * The codes are defined as far as a document is concerned, and measure correctly.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTheDuplicatedCodesAreDefinedAndMeasurable(): void
    {
        $stack = $this->coreStack('core/Times.afm', 'times');

        $this->assertTrue($stack->isCharDefined(160), 'the no-break space');
        $this->assertTrue($stack->isCharDefined(173), 'the soft hyphen');

        // getCharWidth() reports the soft hyphen as zero width by contract (it is only
        // drawn where the text is hyphenated), so the recorded metric is read directly
        $metric = $stack->getCurrentFont();
        $this->bcAssertEqualsWithDelta($metric['cw'][45] ?? null, $metric['cw'][173] ?? null, 0.0001);
        $this->bcAssertEqualsWithDelta($stack->getCharWidth(32), $stack->getCharWidth(160), 0.0001);
    }

    /**
     * A font with the FontSpecific encoding reads its codes from the AFM 'C' column, which
     * has no duplicates to resolve.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAFontSpecificEncodingIsUnaffected(): void
    {
        $stack = $this->coreStack('core/Symbol.afm', 'symbol');
        $font = $stack->getFont($stack->getCurrentFontKey());

        // Symbol declares its own glyphs at 160 and 173 (Euro and arrowvertex), which are
        // neither the space nor the hyphen of the text fonts
        $this->assertNotSame($font['cw'][32] ?? null, $font['cw'][160] ?? null);
    }
}
