<?php

/**
 * CharMetricLookupTest.php
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
 * Looking up the metrics of a character on a byte encoded font.
 *
 * The character code of a glyph is not its codepoint on these fonts (WinAnsi 146 is
 * U+2019), and a font only declares the glyphs it carries: both the "is this character
 * available" question and the glyph box must answer accordingly.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class CharMetricLookupTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/';

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    private function helveticaStack(): Stack
    {
        $this->setupTest();
        new Import(\dirname(__DIR__) . self::MIRROR . 'core/Helvetica.afm');

        $objnum = 1;
        $stack = new Stack(1);
        $stack->insert($objnum, 'helvetica', '', 10, 0, 1);

        return $stack;
    }

    /**
     * A code the font does not encode is not defined.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testUndefinedCodesAreReportedAsSuch(): void
    {
        $stack = $this->helveticaStack();

        $this->assertTrue($stack->isCharDefined(0x41), 'A');
        $this->assertTrue($stack->isCharDefined(0x20), 'space');
        // WinAnsi leaves these bytes undefined, and the control codes are not glyphs
        $this->assertFalse($stack->isCharDefined(0x81));
        $this->assertFalse($stack->isCharDefined(0x8D));
        $this->assertFalse($stack->isCharDefined(0x01));
    }

    /**
     * The character code of a glyph in a byte encoded font is not its codepoint, so a glyph
     * outside Latin-1 is only reachable through the codepoint-keyed map. It is defined all
     * the same, and the width and the box reported for it are its own.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testCodepointsOutsideTheEncodingAreDefinedThroughTheirOwnMap(): void
    {
        $stack = $this->helveticaStack();
        $metric = $stack->getCurrentFont();

        // WinAnsi encodes the right single quotation mark at code 146
        $this->assertTrue($stack->isCharDefined(0x2019), 'the right single quotation mark');
        $this->assertNotEquals($metric['dw'], $stack->getCharWidth(0x2019));
        // a codepoint no glyph of the font carries stays undefined
        $this->assertFalse($stack->isCharDefined(0x4E00), 'a CJK ideograph');
    }

    /**
     * A character the font carries through the codepoint-keyed map is not missing, so it is
     * not substituted.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testCodepointsOutsideTheEncodingAreNotSubstituted(): void
    {
        $stack = $this->helveticaStack();

        $this->assertSame(
            [0x2019],
            $stack->replaceMissingChars([0x2019], [
                0x2019 => [0x27],
            ]),
        );
    }

    /**
     * A substitute the font carries only through the codepoint-keyed map is accepted.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testASubstituteReachableThroughTheCodepointMapIsAccepted(): void
    {
        $stack = $this->helveticaStack();

        $this->assertSame(
            [0x2019],
            $stack->replaceMissingChars([0x4E00], [
                0x4E00 => [0x2019],
            ]),
        );
    }

    /**
     * The width of an undefined code is the default one.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testUndefinedCodesKeepTheDefaultWidth(): void
    {
        $stack = $this->helveticaStack();
        $metric = $stack->getCurrentFont();

        $this->bcAssertEqualsWithDelta($metric['dw'], $stack->getCharWidth(0x81), 0.0001);
    }

    /**
     * A document that supplies a fallback for a character the font does not carry gets it
     * substituted.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testMissingCharactersAreReplacedByTheGivenSubstitutes(): void
    {
        $stack = $this->helveticaStack();

        // 0x81 is not encoded by WinAnsi: the fallback 'A' must take its place
        $this->assertSame(
            [0x41, 0x42],
            $stack->replaceMissingChars([0x81, 0x42], [
                0x81 => [0x8D, 0x41],
            ]),
        );
    }

    /**
     * The glyph box is reachable through the codepoint of the glyph, the way its width is.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testGlyphBoxIsReachableByCodepointAndByEncodingByte(): void
    {
        $stack = $this->helveticaStack();

        $byByte = $stack->getCharBBox(146); // quoteright, at its WinAnsi byte
        $this->assertNotSame([0.0, 0.0, 0.0, 0.0], $byByte);
        $this->assertSame($byByte, $stack->getCharBBox(0x2019), 'the same glyph, by codepoint');

        // a glyph WinAnsi does not encode at all: only the codepoint addresses it
        $this->assertNotSame([0.0, 0.0, 0.0, 0.0], $stack->getCharBBox(0xFB01), 'the fi ligature');

        // a character the font does not carry has no outline to report
        $this->assertSame([0.0, 0.0, 0.0, 0.0], $stack->getCharBBox(0x4E00));
    }
}
