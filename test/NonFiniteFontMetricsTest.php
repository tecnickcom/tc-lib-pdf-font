<?php

/**
 * NonFiniteFontMetricsTest.php
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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A font size, spacing or stretching that is not a finite number.
 *
 * Each is treated as no value, so the current font is inherited or the default applies, the
 * way a null does.
 *
 * @since     2026-08-27
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class NonFiniteFontMetricsTest extends TestUtil
{
    /**
     * Write the definition file of a font with one width.
     */
    private function writeFont(): void
    {
        \file_put_contents(
            $this->getFontPath() . 'measured.json',
            '{"type":"Core","name":"measured","dw":600,"cw":{"65":400}'
            . ',"desc":{"FontBBox":"[0 -200 1000 900]","Ascent":900,"Descent":-200}}',
        );
    }

    /** @return array<string, array{0: float}> */
    public static function provideNonFiniteValue(): array
    {
        return [
            'not a number' => [NAN],
            'infinity' => [INF],
            'negative infinity' => [-INF],
        ];
    }

    /**
     * @throws \Throwable
     */
    #[DataProvider('provideNonFiniteValue')]
    public function testANonFiniteSizeFallsBackToTheDefault(float $size): void
    {
        $this->setupTest();
        $this->writeFont();

        $stack = new Stack(1);
        $objnum = 1;
        $metric = $stack->insert($objnum, 'measured', '', $size);

        $this->assertSame((float) Stack::DEFAULT_SIZE, $metric['size']);
        $this->assertSame('BT /F1 10.000000 Tf ET' . "\r", $metric['out']);
    }

    /**
     * @throws \Throwable
     */
    #[DataProvider('provideNonFiniteValue')]
    public function testANonFiniteSizeInheritsTheCurrentFont(float $size): void
    {
        $this->setupTest();
        $this->writeFont();

        $stack = new Stack(1);
        $objnum = 1;
        $stack->insert($objnum, 'measured', '', 24);
        $metric = $stack->insert($objnum, 'measured', '', $size);

        $this->assertSame(24.0, $metric['size']);
    }

    /**
     * @throws \Throwable
     */
    #[DataProvider('provideNonFiniteValue')]
    public function testANonFiniteSpacingAndStretchingFallBackToTheDefault(float $value): void
    {
        $this->setupTest();
        $this->writeFont();

        $stack = new Stack(1);
        $objnum = 1;
        $metric = $stack->insert($objnum, 'measured', '', 1000, $value, $value);

        $this->assertSame(0.0, $metric['spacing']);
        $this->assertSame(1.0, $metric['stretching']);
        // the widths are measured through both, so they are finite too
        $this->assertSame(400.0, $stack->getCharWidth(65));
        $this->assertSame(400.0, $stack->getOrdArrWidth([65]));
    }

    /**
     * The clone of a font takes the same three values, and normalizes them the same way.
     *
     * @throws FontException
     */
    public function testTheCloneOfAFontRefusesANonFiniteSize(): void
    {
        $this->setupTest();
        $this->writeFont();

        $stack = new Stack(1);
        $objnum = 1;
        $stack->insert($objnum, 'measured', '', 18);
        $metric = $stack->cloneFont($objnum, null, null, NAN, NAN, NAN);

        $this->assertSame(18.0, $metric['size']);
        $this->assertSame(0.0, $metric['spacing']);
        $this->assertSame(1.0, $metric['stretching']);
    }
}
