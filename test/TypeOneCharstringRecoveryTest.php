<?php

/**
 * TypeOneCharstringRecoveryTest.php
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
use Com\Tecnick\Pdf\Font\FontType;
use Com\Tecnick\Pdf\Font\Import;

/**
 * A charstring that runs out of bytes costs its own glyph, not the whole font.
 *
 * An operand of the Type 1 charstring language announces how many bytes follow it. One that
 * runs past the end of its glyph ends that charstring alone, and the glyphs that follow are
 * decoded as usual.
 *
 * A font with no encoding array of its own falls back to cp1252, where 'space' is both 32
 * and 160 and 'hyphen' both 45 and 173, and each code gets the width of the glyph.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TypeOneCharstringRecoveryTest extends TestUtil
{
    /**
     * Encrypt a charstring with the Type 1 charstring key (R = 4330).
     */
    private function encryptCharstring(string $plain): string
    {
        return $this->encrypt($plain, 4330);
    }

    /**
     * Encrypt the eexec section with the Type 1 eexec key (R = 55665).
     */
    private function encryptEexec(string $plain): string
    {
        return $this->encrypt($plain, 55_665);
    }

    private function encrypt(string $plain, int $csr): string
    {
        $cc1 = 52_845;
        $cc2 = 22_719;
        $out = '';
        $len = \strlen($plain);
        for ($idx = 0; $idx < $len; ++$idx) {
            $chr = \ord($plain[$idx]) ^ ($csr >> 8);
            $out .= \chr($chr);
            $csr = ((($chr + $csr) * $cc1) + $cc2) % 65_536;
        }

        return $out;
    }

    /**
     * Build the charstring of a glyph declaring the given advance width through 'hsbw'.
     *
     * The four leading random bytes are the '/lenIV' default. 'hsbw' is opcode 13 and takes
     * the side bearing and the width, both in the 247..250 two-byte form.
     */
    private function charstring(int $width, bool $truncated = false): string
    {
        $operand = static fn(int $val): string => \chr(247 + \intdiv($val - 108, 256)) . \chr(($val - 108) % 256);
        $body = "\x00\x00\x00\x00" . $operand(108) . $operand($width) . "\x0D"; // hsbw
        if ($truncated) {
            // an operand of the 247..250 form announcing a byte that is not there: the
            // glyph ends in the middle of a number, after its width was already read
            $body .= "\xF7";
        }

        return $this->encryptCharstring($body);
    }

    /**
     * Build a charstring that runs out of bytes before it reaches its 'hsbw'.
     */
    private function charstringWithoutWidth(): string
    {
        return $this->encryptCharstring("\x00\x00\x00\x00\xF7");
    }

    /**
     * Assemble a PFB carrying the given charstring entries.
     *
     * @param array<string, string> $glyphs Glyph name => encrypted charstring.
     */
    private function buildPfb(array $glyphs): string
    {
        $clear = "%!PS-AdobeFont-1.0\n/FontName /TestFont def\n/FontBBox {0 -200 1000 800} def\ncurrentfile eexec\n";

        $entries = '';
        foreach ($glyphs as $name => $charstring) {
            $entries .= '/' . $name . ' ' . \strlen($charstring) . ' RD ' . $charstring . " ND\n";
        }

        // no 'dup N /name put' array, so the cp1252 fallback supplies the encoding
        $eplain =
            "dup /Private 8 dict dup begin\n/lenIV 4 def\n/StdVW [80] def\nend\n"
            . '/CharStrings '
            . \count($glyphs)
            . " dict dup begin\n"
            . $entries
            . "end\n";

        $encrypted = $this->encryptEexec($eplain);

        return (
            "\x80\x01"
            . \pack('V', \strlen($clear))
            . $clear
            . "\x80\x02"
            . \pack('V', \strlen($encrypted))
            . $encrypted
            . "\x80\x03"
        );
    }

    /**
     * @param array<string, string> $glyphs
     *
     * @return array<int, int>
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    private function importWidths(array $glyphs, string $name): array
    {
        $path = $this->getFontPath() . $name . '.pfb';
        \file_put_contents($path, $this->buildPfb($glyphs));

        $import = new Import($path, $this->getFontPath(), FontType::Type1, 'cp1252');
        $decoded = $this->decodeDefinition($this->getFontPath() . $import->getFontName() . '.json');

        return $this->intMap($this->arrayMember($decoded, 'cw'));
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testEveryGlyphIsReadWhenNoCharstringIsTruncated(): void
    {
        $this->setupTest();
        $widths = $this->importWidths([
            'A' => $this->charstring(722),
            'B' => $this->charstring(667),
        ], 'intact');

        $this->assertSame(722, $widths[65] ?? null);
        $this->assertSame(667, $widths[66] ?? null);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testATruncatedCharstringDoesNotDiscardTheOtherGlyphs(): void
    {
        $this->setupTest();
        $widths = $this->importWidths([
            'A' => $this->charstring(722, true),
            'B' => $this->charstring(667),
        ], 'truncated');

        // the glyph that follows the broken one is read as usual
        $this->assertSame(667, $widths[66] ?? null);
        // the broken one still reports the width its hsbw declared before it ran out
        $this->assertSame(722, $widths[65] ?? null);
    }

    /**
     * A glyph truncated before its 'hsbw' has no width to report, and still does not stop
     * the ones that follow it.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAGlyphTruncatedBeforeItsWidthIsSkipped(): void
    {
        $this->setupTest();
        $widths = $this->importWidths([
            'A' => $this->charstringWithoutWidth(),
            'B' => $this->charstring(667),
        ], 'nowidth');

        $this->assertArrayNotHasKey(65, $widths);
        $this->assertSame(667, $widths[66] ?? null);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testBothCodesOfADuplicatedWinAnsiNameGetTheWidth(): void
    {
        $this->setupTest();
        $widths = $this->importWidths([
            'space' => $this->charstring(278),
            'hyphen' => $this->charstring(333),
        ], 'duplicates');

        $this->assertSame(278, $widths[32] ?? null, 'space');
        $this->assertSame(278, $widths[160] ?? null, 'no-break space');
        $this->assertSame(333, $widths[45] ?? null, 'hyphen');
        $this->assertSame(333, $widths[173] ?? null, 'soft hyphen');
    }
}
