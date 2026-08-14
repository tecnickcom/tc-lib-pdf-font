<?php

/**
 * SymbolicEncodingSchemeTest.php
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

/**
 * An AFM is font-specific unless it names one of the Latin text encoding schemes.
 *
 * A re-issued symbol font names its scheme after itself ('Symbol', 'Zapfdingbats' in the
 * mirror this repository imports from), and every other symbol typeface names it after
 * whatever its vendor chose, so the known Latin text schemes are the closed set and anything
 * outside it reads its codes from the AFM 'C' column and carries a Symbolic descriptor.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class SymbolicEncodingSchemeTest extends TestUtil
{
    private const SYMBOLIC = 4;

    private const NONSYMBOLIC = 32;

    /**
     * Write a minimal AFM declaring the given name and encoding scheme.
     *
     * The two glyphs are chosen so that the branch taken is visible in the result: 'alpha'
     * has no cp1252 code, so it only gets a metric when the 'C' column is read, while
     * 'space' is reachable either way.
     */
    private function writeAfm(string $file, string $fontName, string $scheme): string
    {
        $path = $this->getFontPath() . $file;
        \file_put_contents(
            $path,
            "StartFontMetrics 4.1\n"
            . 'FontName '
            . $fontName
            . "\n"
            . 'EncodingScheme '
            . $scheme
            . "\n"
            . "FontBBox 0 -200 1000 800\n"
            . "CapHeight 700\n"
            . "XHeight 500\n"
            . "Ascender 800\n"
            . "Descender -200\n"
            . "StartCharMetrics 2\n"
            . "C 32 ; WX 250 ; N space ; B 0 0 0 0 ;\n"
            . "C 97 ; WX 631 ; N alpha ; B 41 -18 622 462 ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n",
        );

        return $path;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    private function importAfm(string $file, string $fontName, string $scheme): array
    {
        $import = new Import($this->writeAfm($file, $fontName, $scheme), $this->getFontPath(), 'Core');

        return $this->decodeDefinition($this->getFontPath() . $import->getFontName() . '.json');
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function flagsOf(array $decoded): int
    {
        return $this->intMember($this->arrayMember($decoded, 'desc'), 'Flags');
    }

    /**
     * @param array<string, mixed> $decoded
     *
     * @return array<int, int>
     */
    private function widthsOf(array $decoded): array
    {
        return $this->intMap($this->arrayMember($decoded, 'cw'));
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testASchemeNamedAfterTheFontIsReadAsFontSpecific(): void
    {
        $this->setupTest();
        $decoded = $this->importAfm('mysymbol.afm', 'MySymbol', 'Symbol');

        $this->assertSame(self::SYMBOLIC, $this->flagsOf($decoded) & self::SYMBOLIC);
        $this->assertSame(0, $this->flagsOf($decoded) & self::NONSYMBOLIC);
        // the 'C' column is honoured, so the glyph that cp1252 does not name keeps its width
        $this->assertSame(631, $this->widthsOf($decoded)[97] ?? null);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTheCanonicalFontSpecificSchemeStillWorks(): void
    {
        $this->setupTest();
        $decoded = $this->importAfm('canonical.afm', 'Canonical', 'FontSpecific');

        $this->assertSame(self::SYMBOLIC, $this->flagsOf($decoded) & self::SYMBOLIC);
        $this->assertSame(631, $this->widthsOf($decoded)[97] ?? null);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAKnownTextSchemeIsReadThroughWinAnsi(): void
    {
        $this->setupTest();
        $decoded = $this->importAfm('winroman.afm', 'MyText', 'WinRoman');

        $this->assertSame(0, $this->flagsOf($decoded) & self::SYMBOLIC);
        $this->assertSame(self::NONSYMBOLIC, $this->flagsOf($decoded) & self::NONSYMBOLIC);
        // 'alpha' has no WinAnsi code, so the 'C' column of 97 is not used for it
        $this->assertArrayNotHasKey(97, $this->widthsOf($decoded));
        $this->assertSame(250, $this->widthsOf($decoded)[32] ?? null);
    }

    /**
     * The key is optional and the text fonts that omit it predate it, so its absence means
     * the Adobe default rather than an unknown encoding.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAnAbsentSchemeIsReadAsText(): void
    {
        $this->setupTest();
        $path = $this->getFontPath() . 'noscheme.afm';
        \file_put_contents(
            $path,
            "StartFontMetrics 4.1\n"
            . "FontName NoScheme\n"
            . "FontBBox 0 -200 1000 800\n"
            . "StartCharMetrics 1\n"
            . "C 97 ; WX 631 ; N alpha ; B 0 0 0 0 ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n",
        );

        $import = new Import($path, $this->getFontPath(), 'Core');
        $decoded = $this->decodeDefinition($this->getFontPath() . $import->getFontName() . '.json');

        $this->assertSame(self::NONSYMBOLIC, $this->flagsOf($decoded) & self::NONSYMBOLIC);
    }

    /**
     * The scheme decides how the code of every metric row is read, so it is taken from the
     * whole file before the rows are processed: a file stating it after the metrics would
     * otherwise read the rows that precede it as text encoded.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testASchemeDeclaredAfterTheMetricsStillClassifiesThem(): void
    {
        $this->setupTest();
        $path = $this->getFontPath() . 'latescheme.afm';
        \file_put_contents(
            $path,
            "StartFontMetrics 4.1\n"
            . "FontName LateScheme\n"
            . "FontBBox 0 -200 1000 800\n"
            . "StartCharMetrics 1\n"
            . "C 97 ; WX 631 ; N alpha ; B 0 0 0 0 ;\n"
            . "EndCharMetrics\n"
            . "EncodingScheme FontSpecific\n"
            . "EndFontMetrics\n",
        );

        $import = new Import($path, $this->getFontPath(), 'Core');
        $decoded = $this->decodeDefinition($this->getFontPath() . $import->getFontName() . '.json');

        $this->assertSame(self::SYMBOLIC, $this->flagsOf($decoded) & self::SYMBOLIC);
        // the 'C' column is honoured, as it is for a scheme declared before the metrics
        $this->assertSame(631, $this->widthsOf($decoded)[97] ?? null);
    }

    /**
     * A CharMetrics row may declare its code in hexadecimal under the 'CH' key.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAHexadecimalCharacterCodeIsRead(): void
    {
        $this->setupTest();
        $path = $this->getFontPath() . 'hexcode.afm';
        \file_put_contents(
            $path,
            "StartFontMetrics 4.1\n"
            . "FontName HexCode\n"
            . "EncodingScheme FontSpecific\n"
            . "FontBBox 0 -200 1000 800\n"
            . "StartCharMetrics 2\n"
            . "CH <61> ; WX 631 ; N alpha ; B 0 0 0 0 ;\n"
            . "CH <zz> ; WX 400 ; N beta ; B 0 0 0 0 ;\n"
            . "EndCharMetrics\n"
            . "EndFontMetrics\n",
        );

        $import = new Import($path, $this->getFontPath(), 'Core');
        $widths = $this->widthsOf($this->decodeDefinition($this->getFontPath() . $import->getFontName() . '.json'));
        $this->assertSame(631, $widths[0x61] ?? null);
        // an unparsable code is no code, and must not become the code zero
        $this->assertArrayNotHasKey(0, $widths);
    }
}
