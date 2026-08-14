<?php

/**
 * EncodingDifferencesTest.php
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
 * Encoding differences of a byte encoded font.
 *
 * The /Differences array names the codes of the font encoding that do not hold the glyph
 * WinAnsi puts at them. A code is written before a name only when it does not follow the
 * code of the name before it, so the runs of consecutive codes are stated once.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class EncodingDifferencesTest extends TestUtil
{
    private const MIRROR = 'util/vendor/tecnickcom/tc-font-mirror/freefont/FreeSans.ttf';

    /**
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    private function importWithEncoding(string $encoding): array
    {
        $this->setupTest();
        $import = new Import(\dirname(__DIR__) . '/' . self::MIRROR, $this->getFontPath(), 'TrueType', $encoding);

        return $this->decodeDefinition($this->getFontPath() . $import->getFontName() . '.json');
    }

    /** @throws \Throwable */
    public function testAnEncodingThatIsNotWinAnsiStatesItsDifferences(): void
    {
        $decoded = $this->importWithEncoding('cp1250');

        $this->assertSame('cp1250', $this->stringMember($decoded, 'enc'));
        // Codes 140 and 141 differ and are consecutive, so only the first is written, while
        // 143 opens a new run. The last two codes of the table close it the same way.
        $this->assertSame('131 /.notdef 136 /.notdef 140 /Sacute /Tcaron 143 /Zacute 152 /.notdef'
        . ' 156 /sacute /tcaron 159 /zacute 161 /caron /breve /Lslash 165 /Aogonek'
        . ' 170 /Scedilla 175 /Zdotaccent 178 /ogonek /lslash 185 /aogonek /scedilla'
        . ' 188 /Lcaron /hungarumlaut /lcaron /zdotaccent /Racute 195 /Abreve'
        . ' 197 /Lacute /Cacute 200 /Ccaron 202 /Eogonek 204 /Ecaron 207 /Dcaron'
        . ' /Dcroat /Nacute /Ncaron 213 /Ohungarumlaut 216 /Rcaron /Uring'
        . ' 219 /Uhungarumlaut 222 /Tcommaaccent 224 /racute 227 /abreve'
        . ' 229 /lacute /cacute 232 /ccaron 234 /eogonek 236 /ecaron 239 /dcaron'
        . ' /dcroat /nacute /ncaron 245 /ohungarumlaut 248 /rcaron /uring'
        . ' 251 /uhungarumlaut 254 /tcommaaccent /dotaccent ', $this->stringMember($decoded, 'diff'));
    }

    /**
     * WinAnsi is the base encoding of the emitted dictionary, so a font using it states no
     * difference at all.
     *
     * @throws \Throwable
     */
    public function testTheBaseEncodingStatesNoDifference(): void
    {
        $this->assertSame('', $this->stringMember($this->importWithEncoding('cp1252'), 'diff'));
    }
}
