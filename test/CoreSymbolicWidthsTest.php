<?php

/**
 * CoreSymbolicWidthsTest.php
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

/**
 * The character codes of an AFM core font.
 *
 * A font declaring the FontSpecific encoding is emitted without an /Encoding entry, so a
 * content stream selects its glyphs through the built-in encoding declared by the AFM 'C'
 * column. Every other core font is emitted as WinAnsiEncoding, where the glyph name
 * selects the byte.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class CoreSymbolicWidthsTest extends TestUtil
{
    private const MIRROR = 'util/vendor/tecnickcom/tc-font-mirror/core/';

    /**
     * Import a bundled AFM font and return its widths indexed by character code.
     *
     * @return array<int, int>
     *
     * @throws \Throwable
     */
    private function importWidths(string $file, string $encoding): array
    {
        $this->setupTest();
        $import = new Import(\dirname(__DIR__) . '/' . self::MIRROR . $file, $this->getFontPath(), '', $encoding);

        return $import->getFontMetrics()['cw'];
    }

    /**
     * Symbol declares 'C 65 ; WX 722 ; N Alpha', so byte 65 is 722 wide. Keying the width
     * on the WinAnsi byte of the glyph name instead files it under a byte no content
     * stream of this font can select, and leaves 65 with the default width.
     *
     * @throws \Throwable
     */
    public function testSymbolWidthsAreKeyedOnTheBuiltInEncoding(): void
    {
        $cwd = $this->importWidths('Symbol.afm', 'symbol');

        $this->assertSame(722, $cwd[65] ?? 0, 'C 65 = Alpha');
        $this->assertSame(460, $cwd[183] ?? 0, 'C 183 = bullet');
        $this->assertSame(549, $cwd[45] ?? 0, 'C 45 = minus');
        $this->assertSame(713, $cwd[34] ?? 0, 'C 34 = universal');
    }

    /**
     * No ZapfDingbats glyph name but 'space' appears in WinAnsi, so keying on the name
     * collapsed the whole font onto a single width.
     *
     * @throws \Throwable
     */
    public function testZapfDingbatsKeepsTheWidthOfEveryGlyph(): void
    {
        $cwd = $this->importWidths('ZapfDingbats.afm', '');

        $this->assertSame(980, $cwd[36] ?? 0, 'C 36 = a3');
        $this->assertSame(789, $cwd[97] ?? 0, 'C 97 = a60');
        $this->assertSame(278, $cwd[32] ?? 0, 'C 32 = space');
        $this->assertGreaterThan(100, \count(\array_unique($cwd)), 'the widths must not collapse');
    }

    /**
     * A text core font is emitted as WinAnsiEncoding, so the glyph name keeps selecting
     * the byte: the AFM column, which describes the AdobeStandardEncoding, must not be
     * used. 'quoteright' is the clearest case: AFM code 39, WinAnsi byte 146.
     *
     * @throws \Throwable
     */
    public function testTextCoreFontWidthsStayKeyedOnWinAnsi(): void
    {
        $cwd = $this->importWidths('Helvetica.afm', 'cp1252');

        // the AFM lists quoteright at code 39 and quotesingle at 169; WinAnsi swaps them
        $this->assertSame(222, $cwd[146] ?? 0, 'quoteright sits at the WinAnsi byte 146');
        $this->assertSame(191, $cwd[39] ?? 0, 'byte 39 is quotesingle in WinAnsi');
        $this->assertSame(667, $cwd[65] ?? 0, 'A');
        $this->assertSame(278, $cwd[32] ?? 0, 'space');
    }
}
