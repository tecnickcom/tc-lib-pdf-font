<?php

/**
 * GidEncodingTest.php
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

/**
 * Tests for the CID == GID encoding of the TrueTypeUnicode fonts
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class GidEncodingTest extends TestUtil
{
    protected function getMirrorPath(): string
    {
        return \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';
    }

    /**
     * Returns a font stack with FreeSerif loaded as the current font.
     *
     * FreeSerif carries a format 12 cmap with supplementary-plane characters.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    protected function getUnicodeStack(int $encodingId = 1): \Com\Tecnick\Pdf\Font\Stack
    {
        $this->setupTest();
        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1.0, false, true, false);
        new \Com\Tecnick\Pdf\Font\Import(
            $this->getMirrorPath() . 'freefont/FreeSerif.ttf',
            '',
            '',
            '',
            32,
            3,
            $encodingId,
        );
        $stack->insert($objnum, 'freeserif', '', 12, null, null, '', false);
        return $stack;
    }

    /**
     * Returns the PDF font objects with the stream data replaced by a placeholder,
     * so that a failure does not dump the embedded font program.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws FontException
     */
    protected function getFontDicts(\Com\Tecnick\Pdf\Font\Stack $stack): string
    {
        $block = $this->getFontsBlock($stack);
        $dicts = '';
        $pos = 0;
        while (($start = \strpos($block, " stream\n", $pos)) !== false) {
            $start += 8;
            $end = \strpos($block, "\nendstream", $start);
            if ($end === false) {
                break;
            }

            $dicts .= \substr($block, $pos, $start - $pos) . '[STREAM]';
            $pos = $end;
        }

        return $dicts . \substr($block, $pos);
    }

    /**
     * Returns the decoded ToUnicode CMap of the first font of the block.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws FontException
     */
    protected function getToUnicodeCMap(\Com\Tecnick\Pdf\Font\Stack $stack): string
    {
        $block = $this->getFontsBlock($stack);

        $matches = [];
        $this->assertSame(1, \preg_match('#/ToUnicode (\d+) 0 R#', $this->getFontDicts($stack), $matches));

        $objpos = \strpos($block, "\n" . ($matches[1] ?? '') . " 0 obj\n");
        $this->assertNotFalse($objpos);
        $start = \strpos($block, " stream\n", $objpos);
        $this->assertNotFalse($start);
        $start += 8;
        $end = \strpos($block, "\nendstream", $start);
        $this->assertNotFalse($end);

        $cmap = \gzuncompress(\substr($block, $start, $end - $start));
        $this->assertNotFalse($cmap);
        return $cmap;
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws FontException
     */
    protected function getFontsBlock(\Com\Tecnick\Pdf\Font\Stack $stack): string
    {
        $objnum = 100;
        $out = new \Com\Tecnick\Pdf\Font\Output(
            $stack->getFonts(),
            $objnum,
            new \Com\Tecnick\Pdf\Encrypt\Encrypt(false),
            null,
        );
        return $out->getFontsBlock();
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testIsCurrentGidEncoded(): void
    {
        $stack = $this->getUnicodeStack();
        $this->assertTrue($stack->isCurrentGidEncoded());
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testCoreFontIsNotGidEncoded(): void
    {
        $this->setupTest();
        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1.0, false, true, false);
        new \Com\Tecnick\Pdf\Font\Import($this->getMirrorPath() . 'core/Helvetica.afm');
        $stack->insert($objnum, 'helvetica', '', 12, null, null, '', false);
        $this->assertFalse($stack->isCurrentGidEncoded());
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testGetGidForOrd(): void
    {
        $stack = $this->getUnicodeStack();

        // The glyph indices are read from the CIDToGIDMap artifact of the font.
        $gidA = $stack->getGidForOrd(0x41); // 'A'
        $gidB = $stack->getGidForOrd(0x42); // 'B'
        $this->assertGreaterThan(0, $gidA);
        $this->assertGreaterThan(0, $gidB);
        $this->assertNotSame($gidA, $gidB);
        $this->assertLessThanOrEqual(0xFFFF, $gidA);

        // The lookup is stable.
        $this->assertSame($gidA, $stack->getGidForOrd(0x41));

        // A codepoint without a glyph resolves to .notdef.
        $this->assertSame(0, $stack->getGidForOrd(0xE000)); // private use area
        $this->assertSame(0, $stack->getGidForOrd(-1));
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testGetGidForSupplementaryOrd(): void
    {
        // The supplementary-plane characters are only mapped by the format 12 cmap
        // subtable, which requires the font to be converted with encoding_id 10.
        $stack = $this->getUnicodeStack(10);

        $gid = $stack->getGidForOrd(0x1_0330); // GOTHIC LETTER AHSA
        $this->assertGreaterThan(0, $gid);
        $this->assertLessThanOrEqual(0xFFFF, $gid);

        $this->assertSame(0, $stack->getGidForOrd(0x10_FFFF)); // unassigned
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testSupplementaryOrdIsNotMappedWithoutFormat12(): void
    {
        // The default conversion selects the BMP only cmap subtable.
        $stack = $this->getUnicodeStack();
        $this->assertSame(0, $stack->getGidForOrd(0x1_0330));
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testOrdArrToGidStr(): void
    {
        $stack = $this->getUnicodeStack(10);

        $ordarr = [0x41, 0x20, 0x1_0330];
        $str = $stack->ordArrToGidStr($ordarr);

        // Each character is encoded as a 2-byte big-endian glyph index.
        $this->assertSame(6, \strlen($str));

        $expected = '';
        foreach ($ordarr as $ord) {
            $gid = $stack->getGidForOrd($ord);
            $expected .= \chr($gid >> 8) . \chr($gid & 0xFF);
        }

        $this->assertSame($expected, $str);

        // Every glyph is recorded with the codepoint it was encoded from.
        $font = $stack->getFont($stack->getCurrentFontKey());
        $this->assertCount(3, $font['usedgid']);
        foreach ($ordarr as $ord) {
            $this->assertSame($ord, $font['usedgid'][$stack->getGidForOrd($ord)] ?? null);
        }
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testOrdArrToGidStrSkipsMissingGlyphs(): void
    {
        $stack = $this->getUnicodeStack();

        $str = $stack->ordArrToGidStr([0xE000]);

        // A codepoint without a glyph is encoded as .notdef and is not recorded,
        // since there is no glyph to describe in the output.
        $this->assertSame("\x00\x00", $str);
        $font = $stack->getFont($stack->getCurrentFontKey());
        $this->assertSame([], $font['usedgid']);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \RangeException
     */
    public function testOutputUsesIdentityCidToGidMap(): void
    {
        $stack = $this->getUnicodeStack(10);
        $stack->ordArrToGidStr([0x41, 0x20, 0x1_0330]);

        $dicts = $this->getFontDicts($stack);
        $this->assertStringContainsString('/CIDToGIDMap /Identity', $dicts);
        // The CIDToGIDMap stream is not embedded any more.
        $this->assertStringNotContainsString('/CIDToGIDMap 1', $dicts);

        // The ToUnicode CMap maps the glyph indices back to the codepoints.
        $cmap = $this->getToUnicodeCMap($stack);
        $gidA = $stack->getGidForOrd(0x41);
        $gidGothic = $stack->getGidForOrd(0x1_0330);
        $this->assertStringContainsString(\sprintf('<%04x> <0041>', $gidA), $cmap);
        // A supplementary-plane codepoint is expressed as a surrogate pair.
        $this->assertStringContainsString(\sprintf('<%04x> <d800df30>', $gidGothic), $cmap);
        $this->assertStringContainsString('3 beginbfchar', $cmap);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \RangeException
     */
    public function testOutputWidthsAreKeyedByGid(): void
    {
        $stack = $this->getUnicodeStack();
        $stack->ordArrToGidStr([0x41]);

        $key = $stack->getCurrentFontKey();
        $gidA = $stack->getGidForOrd(0x41);
        $widthA = $stack->getFont($key)['cw'][0x41] ?? 0;

        $matches = [];
        $this->assertSame(1, \preg_match('#/W \[(.*?) \]#s', $this->getFontDicts($stack), $matches));
        $warr = \trim($matches[1] ?? '');

        // Only the used glyph is listed, keyed by its glyph index and written in the
        // interval form 'first last width'.
        $this->assertSame($gidA . ' ' . $gidA . ' ' . $widthA, $warr);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \RangeException
     */
    public function testOutputOfAnUnusedFont(): void
    {
        $stack = $this->getUnicodeStack();

        // A font that the document never uses has no glyph to describe.
        $this->assertStringContainsString('/W [ ]', $this->getFontDicts($stack));
        $cmap = $this->getToUnicodeCMap($stack);
        $this->assertStringNotContainsString('beginbfchar', $cmap);
        $this->assertStringContainsString('begincodespacerange', $cmap);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportStoresTheSupplementaryMap(): void
    {
        $this->setupTest();
        new \Com\Tecnick\Pdf\Font\Import($this->getMirrorPath() . 'freefont/FreeSerif.ttf', '', '', '', 32, 3, 10);

        $data = \file_get_contents($this->getFontPath() . 'freeserif.json');
        $this->assertNotFalse($data);
        /** @var array<string, mixed> $json */
        $json = \json_decode($data, true);

        $this->assertArrayHasKey('ctgu', $json);
        /** @var array<int, int> $ctgu */
        $ctgu = $json['ctgu'] ?? [];
        $this->assertNotEmpty($ctgu);

        // Only the supplementary-plane codepoints are stored, the others fit the
        // 16-bit CIDToGIDMap table.
        foreach (\array_keys($ctgu) as $ord) {
            $this->assertGreaterThan(0xFFFF, (int) $ord);
        }

        $this->assertArrayHasKey(0x1_0330, $ctgu);
        $this->assertGreaterThan(0, $ctgu[0x1_0330] ?? 0);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportStoresNoSupplementaryMapForABmpCmap(): void
    {
        $this->setupTest();
        new \Com\Tecnick\Pdf\Font\Import($this->getMirrorPath() . 'freefont/FreeSerif.ttf');

        $data = \file_get_contents($this->getFontPath() . 'freeserif.json');
        $this->assertNotFalse($data);
        /** @var array<string, mixed> $json */
        $json = \json_decode($data, true);

        $this->assertArrayNotHasKey('ctgu', $json);
    }
}
