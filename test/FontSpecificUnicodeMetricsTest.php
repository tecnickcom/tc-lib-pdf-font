<?php

/**
 * FontSpecificUnicodeMetricsTest.php
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

use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * A font whose character codes are its own must not carry the codepoint keyed metric maps.
 *
 * The Adobe Glyph List codepoint of a glyph name has no relation to the code such a font
 * gives that glyph, and Stack reads 'cwu' before 'cw' and 'cbboxu' before 'cbbox'.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FontSpecificUnicodeMetricsTest extends TestUtil
{
    private const MIRROR = 'util/vendor/tecnickcom/tc-font-mirror/';

    private function mirror(string $sub): string
    {
        return \dirname(__DIR__) . '/' . self::MIRROR . $sub;
    }

    /**
     * Codes of Symbol whose glyph name is absent from the Adobe Glyph List subset, while
     * the list gives the same code to a name the font uses for another glyph.
     *
     * @return array<int, array{int, int, string, string}>
     */
    public static function symbolCollisionProvider(): array
    {
        return [
            [0xB5, 713, 'proportional', 'mu'],
            [0xD7, 250, 'dotmath',      'multiply'],
            [0xF7, 384, 'parenrightex', 'divide'],
            [0xAC, 987, 'arrowleft',    'logicalnot'],
        ];
    }

    /**
     * @throws \Throwable
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('symbolCollisionProvider')]
    public function testSymbolMeasuresItsOwnCodes(int $code, int $width, string $own, string $agl): void
    {
        $this->setupTest();
        new Import($this->mirror('core/Symbol.afm'), $this->getFontPath(), 'Core');

        $stack = new Stack(1.0, false, false, false);
        $objnum = 0;
        $stack->insert($objnum, 'symbol', '', 1000.0);

        $this->bcAssertEqualsWithDelta(
            $width,
            $stack->getCharWidth($code),
            0.0001,
            'code ' . $code . ' is ' . $own . ', not the ' . $agl . ' of the Adobe Glyph List',
        );
    }

    /**
     * @throws \Throwable
     */
    public function testAFontSpecificAfmCarriesNoCodepointKeyedMap(): void
    {
        $this->setupTest();
        $import = new Import($this->mirror('core/Symbol.afm'), $this->getFontPath(), 'Core');
        $metrics = $import->getFontMetrics();

        $this->assertSame([], $metrics['cwu']);
        $this->assertSame([], $metrics['cbboxu']);
        // the codes of the font itself are still recorded
        $this->assertSame(722, $metrics['cw'][65] ?? 0, 'C 65 = Alpha');
    }

    /**
     * A Latin text AFM keeps the map: its codes are those of an encoding, so the codepoint
     * of a glyph name is the one the document measures by.
     *
     * @throws \Throwable
     */
    public function testATextAfmKeepsTheCodepointKeyedMap(): void
    {
        $this->setupTest();
        $import = new Import($this->mirror('core/Helvetica.afm'), $this->getFontPath(), 'Core');
        $metrics = $import->getFontMetrics();

        $this->assertNotSame([], $metrics['cwu']);
        // WinAnsi 146 is the 'quoteright' the Adobe Glyph List puts at U+2019
        $this->assertSame($metrics['cw'][146] ?? 0, $metrics['cwu'][0x2019] ?? 0);
    }

    /**
     * A Type1 font emitted without an /Encoding entry addresses its glyphs through the
     * built-in encoding array of the program, so it carries no codepoint keyed map either.
     *
     * @throws \Throwable
     */
    public function testASymbolicTypeOneCarriesNoCodepointKeyedMap(): void
    {
        $this->setupTest();
        // flags 4 marks the font symbolic, which leaves the encoding empty
        $import = new Import($this->mirror('pdfa/pfb/PDFASymbol.pfb'), $this->getFontPath(), 'Type1', '', 4);
        $metrics = $import->getFontMetrics();

        $this->assertSame('', $metrics['enc']);
        $this->assertSame([], $metrics['cwu']);
        $this->assertNotSame([], $metrics['cw']);
    }

    /**
     * @throws \Throwable
     */
    public function testANonSymbolicTypeOneKeepsTheCodepointKeyedMap(): void
    {
        $this->setupTest();
        $import = new Import($this->mirror('pdfa/pfb/PDFAHelvetica.pfb'), $this->getFontPath(), 'Type1', 'cp1252');
        $metrics = $import->getFontMetrics();

        $this->assertSame('cp1252', $metrics['enc']);
        $this->assertNotSame([], $metrics['cwu']);
        $this->assertSame($metrics['cw'][146] ?? 0, $metrics['cwu'][0x2019] ?? 0);
    }
}
