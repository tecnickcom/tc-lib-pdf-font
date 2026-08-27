<?php

/**
 * FractionalWidthTest.php
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

use Com\Tecnick\Pdf\Encrypt\Exception as EncException;

/**
 * The /W array is built from integers, whatever the definition file spells the widths as.
 *
 * A definition file may spell a width as a float or as a numeric string, and each width is
 * rounded to an integer before it reaches the /W array.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @phpstan-import-type TFontData from \Com\Tecnick\Pdf\Font\Load
 */
class FractionalWidthTest extends TestUtil
{
    /**
     * Build a font whose width map holds values the type does not promise.
     *
     * @param array<int, mixed> $cwd     Character widths as the definition file spells them.
     * @param array<int, int>   $usedgid Glyph index => codepoint it was encoded from.
     *
     * @return TFontData
     */
    private function fontWith(array $cwd, int $dwt, array $usedgid = []): array
    {
        /** @var TFontData $font */
        $font = [
            'cw' => $cwd,
            'dw' => $dwt,
            'subset' => false,
            'subsetchars' => [],
            'usedgid' => $usedgid,
        ];

        return $font;
    }

    public function testFractionalWidthsAreRoundedToIntegers(): void
    {
        $harness = new OutUtilTestHarness();
        $out = $harness->runCharWidths($this->fontWith([32 => 277.83, 33 => 300.4], 600));

        $this->assertSame('/W [ 32 [ 278 300 ] ]', $out);
    }

    /**
     * The compact interval form is chosen by counting the distinct widths of a run.
     */
    public function testEqualFractionalWidthsStillCollapseIntoOneInterval(): void
    {
        $harness = new OutUtilTestHarness();
        $out = $harness->runCharWidths($this->fontWith([32 => 300.5, 33 => 300.5, 34 => 300.5], 600));

        $this->assertSame('/W [ 32 34 301 ]', $out);
    }

    /**
     * A width equal to the default is carried by /DW and must not be listed again.
     */
    public function testAFractionalWidthEqualToTheDefaultIsOmitted(): void
    {
        $harness = new OutUtilTestHarness();
        $out = $harness->runCharWidths($this->fontWith([32 => 600.0, 33 => 400], 600));

        $this->assertSame('/W [ 33 33 400 ]', $out);
    }

    /**
     * The glyph-index keyed builder normalizes the same way, so a font emitted by either
     * path reports the same widths.
     */
    public function testGidWidthsAreNormalizedTheSameWay(): void
    {
        $harness = new OutUtilTestHarness();
        $out = $harness->runGidWidths($this->fontWith([65 => 277.83], 600, [7 => 65]));

        $this->assertSame('/W [ 7 7 278 ]', $out);
    }

    /**
     * A definition file may also spell a width as a numeric string; anything that is not a
     * number reads as zero.
     */
    public function testNonNumericWidthsAreReadAsZero(): void
    {
        $harness = new OutUtilTestHarness();
        $out = $harness->runCharWidths($this->fontWith([32 => '400', 33 => 'wide'], 600));

        $this->assertSame('/W [ 32 [ 400 0 ] ]', $out);
    }

    /**
     * The /Widths array of a simple font is built from the same integers, so a fractional
     * width is rounded there too rather than truncated.
     *
     * @throws EncException
     */
    public function testTheWidthsArrayOfASimpleFontIsRoundedTheSameWay(): void
    {
        /** @var TFontData $font */
        $font = \array_replace($this->fontWith([32 => 277.83, 33 => 300.4], 600), [
            'dw' => 600.6,
            'diff_n' => 0,
            'desc' => [],
            'enc' => '',
            'file' => '',
            'file_n' => 0,
            'i' => 1,
            'n' => 1,
            'name' => 'testfont',
            'type' => 'TrueType',
        ]);

        $harness = new OutputTestOutFont();
        $out = $harness->runGetTrueType($font);

        // codes 32 and 33, then the default width of every code the font does not carry
        $this->assertStringContainsString('[278 300 601 601', $out);
    }
}
