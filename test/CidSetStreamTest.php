<?php

/**
 * CidSetStreamTest.php
 *
 * @since     2026-11-02
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
use Com\Tecnick\Pdf\Encrypt\Exception as EncException;
use Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Tests for the CIDSet stream of the subset TrueTypeUnicode fonts
 *
 * @since     2026-11-02
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @phpstan-import-type TFontData from \Com\Tecnick\Pdf\Font\Load
 */
class CidSetStreamTest extends TestUtil
{
    protected function getMirrorPath(): string
    {
        return \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/';
    }

    /**
     * Returns a font stack with FreeSerif loaded as the current font and 'A', 'B' encoded.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    protected function getStack(bool $subset): \Com\Tecnick\Pdf\Font\Stack
    {
        $this->setupTest();
        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1.0, $subset, true, true);
        new \Com\Tecnick\Pdf\Font\Import($this->getMirrorPath() . 'freefont/FreeSerif.ttf');
        $stack->insert($objnum, 'freeserif', '', 12);
        $stack->ordArrToGidStr([0x41, 0x42]);
        return $stack;
    }

    /**
     * @param array<string, TFontData> $fonts
     *
     * @throws FileException
     * @throws EncException
     * @throws FontException
     */
    protected function getOutput(array $fonts, bool $cidset): \Com\Tecnick\Pdf\Font\Output
    {
        return new \Com\Tecnick\Pdf\Font\Output(
            $fonts,
            100,
            new \Com\Tecnick\Pdf\Encrypt\Encrypt(false),
            null,
            null,
            $cidset,
        );
    }

    /**
     * Returns the PDF objects with the stream data removed.
     */
    protected function getDicts(string $block): string
    {
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
     * Returns the object numbers referenced by the /CIDSet entries.
     *
     * @return list<int>
     */
    protected function getCidSetRefs(string $block): array
    {
        $matches = [];
        \preg_match_all('#/CIDSet (\d+) 0 R#', $this->getDicts($block), $matches);
        $refs = [];
        foreach ($matches[1] ?? [] as $ref) {
            $refs[] = (int) $ref;
        }

        return $refs;
    }

    /**
     * Returns the object number referenced by the only /CIDSet entry.
     */
    protected function getCidSetRef(string $block): int
    {
        $refs = $this->getCidSetRefs($block);
        $this->assertCount(1, $refs);
        return $refs[0] ?? 0;
    }

    /**
     * Returns the dictionary and the raw stream data of an object.
     *
     * @return array{0: string, 1: string}
     */
    protected function getStreamObject(string $block, int $objnum): array
    {
        $objpos = \strpos($block, "\n" . $objnum . " 0 obj\n");
        $this->assertNotFalse($objpos);
        $start = \strpos($block, " stream\n", $objpos);
        $this->assertNotFalse($start);
        $dict = \substr($block, $objpos, $start - $objpos);
        $start += 8;
        $end = \strpos($block, "\nendstream", $start);
        $this->assertNotFalse($end);
        return [$dict, \substr($block, $start, $end - $start)];
    }

    /**
     * Returns the CIDs flagged in a CIDSet bit string.
     *
     * @return array<int, int>
     */
    protected function decodeCids(string $bits): array
    {
        $cids = [];
        $len = \strlen($bits);
        for ($idx = 0; $idx < $len; ++$idx) {
            $byte = \ord($bits[$idx]);
            for ($bit = 0; $bit < 8; ++$bit) {
                if (($byte & (0x80 >> $bit)) === 0) {
                    continue;
                }

                $cids[] = ($idx * 8) + $bit;
            }
        }

        return $cids;
    }

    /**
     * Returns the CIDs of a compressed CIDSet object.
     *
     * @return array<int, int>
     */
    protected function getCompressedCids(string $block, int $objnum): array
    {
        [$dict, $data] = $this->getStreamObject($block, $objnum);
        $this->assertStringContainsString('/Filter /FlateDecode', $dict);
        $bits = \gzuncompress($data);
        $this->assertNotFalse($bits);
        return $this->decodeCids($bits);
    }

    /**
     * @param array<int, int> $usedgid
     *
     * @return array<int, int>
     */
    protected function expectedGidCids(array $usedgid): array
    {
        $cids = \array_keys($usedgid);
        $cids[] = 0;
        \sort($cids);
        return $cids;
    }

    /**
     * @throws FileException
     * @throws EncException
     * @throws FontException
     * @throws \RangeException
     */
    public function testSubsetGidFontHasCidSet(): void
    {
        $stack = $this->getStack(true);
        $font = $stack->getFont($stack->getCurrentFontKey());
        $this->assertTrue($font['gidenc']);

        $output = $this->getOutput($stack->getFonts(), true);
        $block = $output->getFontsBlock();
        $this->assertStringContainsString('/BaseFont /AAAAAB+', $block);
        $ref = $this->getCidSetRef($block);
        // the CIDSet stream directly follows the font descriptor
        $this->assertSame($output->getObjectNumber(), $ref);
        $this->assertStringContainsString("\n" . ($ref - 1) . " 0 obj\n<< /Type /FontDescriptor", $block);
        $this->assertSame($this->expectedGidCids($font['usedgid']), $this->getCompressedCids($block, $ref));
    }

    /**
     * @throws FileException
     * @throws EncException
     * @throws FontException
     * @throws \RangeException
     */
    public function testCidSetIsOmittedByDefault(): void
    {
        $stack = $this->getStack(true);
        $fonts = $stack->getFonts();

        $block = $this->getOutput($fonts, false)->getFontsBlock();
        $this->assertSame([], $this->getCidSetRefs($block));

        $out = new \Com\Tecnick\Pdf\Font\Output($fonts, 100, new \Com\Tecnick\Pdf\Encrypt\Encrypt(false));
        $this->assertSame($block, $out->getFontsBlock());
    }

    /**
     * @throws FileException
     * @throws EncException
     * @throws FontException
     * @throws \RangeException
     */
    public function testFullFontHasNoCidSet(): void
    {
        $stack = $this->getStack(false);
        $output = $this->getOutput($stack->getFonts(), true);
        $this->assertSame([], $this->getCidSetRefs($output->getFontsBlock()));
    }

    /**
     * @throws FileException
     * @throws EncException
     * @throws FontException
     * @throws \RangeException
     */
    public function testCidSetAddsOneObject(): void
    {
        $stack = $this->getStack(true);
        $fonts = $stack->getFonts();
        $without = $this->getOutput($fonts, false)->getObjectNumber();
        $this->assertSame($without + 1, $this->getOutput($fonts, true)->getObjectNumber());
    }

    /**
     * @throws FileException
     * @throws EncException
     * @throws FontException
     * @throws \RangeException
     */
    public function testCidSetFollowsCidToGidMapStream(): void
    {
        $stack = $this->getStack(true);
        $fonts = $stack->getFonts();
        $key = $stack->getCurrentFontKey();
        // a font keyed by codepoint embeds the CIDToGIDMap stream
        $fonts[$key]['gidenc'] = false;
        // a codepoint above the 2-byte CID range is not a CID
        $fonts[$key]['cw'][0x1_0330] = 500;
        $fonts[$key]['subsetchars'][0x1_0330] = true;

        $output = $this->getOutput($fonts, true);
        $block = $output->getFontsBlock();
        $dicts = $this->getDicts($block);

        $matches = [];
        $this->assertSame(1, \preg_match('#/CIDToGIDMap (\d+) 0 R#', $dicts, $matches));
        $ctgobj = (int) ($matches[1] ?? 0);
        $ref = $this->getCidSetRef($block);
        $this->assertSame($ctgobj + 1, $ref);
        $this->assertSame($ref, $output->getObjectNumber());

        $expected = [0];
        foreach (\array_keys($fonts[$key]['cw']) as $cid) {
            if ($cid > 0xFFFF || !($fonts[$key]['subsetchars'][$cid] ?? false)) {
                continue;
            }

            $expected[] = $cid;
        }

        $expected = \array_values(\array_unique($expected));
        \sort($expected);
        $this->assertContains(0x41, $expected);
        $this->assertSame($expected, $this->getCompressedCids($block, $ref));
    }

    /**
     * @throws FileException
     * @throws EncException
     * @throws FontException
     * @throws \RangeException
     */
    public function testUncompressedCidSet(): void
    {
        $stack = $this->getStack(true);
        $fonts = $stack->getFonts();
        $key = $stack->getCurrentFontKey();
        $fonts[$key]['compress'] = false;

        $block = $this->getOutput($fonts, true)->getFontsBlock();
        [$dict, $data] = $this->getStreamObject($block, $this->getCidSetRef($block));
        $this->assertStringNotContainsString('/Filter', $dict);
        $this->assertStringContainsString('/Length ' . \strlen($data) . ' ', $dict);
        $this->assertSame($this->expectedGidCids($stack->getFont($key)['usedgid']), $this->decodeCids($data));
    }

    /**
     * @throws FileException
     * @throws EncException
     * @throws FontException
     * @throws \RangeException
     */
    public function testSharedFontFileCidSetIsTheUnion(): void
    {
        $stack = $this->getStack(true);
        $fonts = $stack->getFonts();
        $key = $stack->getCurrentFontKey();
        $gidC = $stack->getGidForOrd(0x43);
        $this->assertGreaterThan(0, $gidC);

        // a second font backed by the same program, referencing another glyph
        $font = $stack->getFont($key);
        $other = $font;
        $other['i'] = $font['i'] + 1;
        $other['n'] = 90;
        $other['usedgid'] = [$gidC => 0x43];
        $other['subsetchars'][0x43] = true;
        $fonts[$key . 'X'] = $other;

        $block = $this->getOutput($fonts, true)->getFontsBlock();
        $refs = $this->getCidSetRefs($block);
        $this->assertCount(2, $refs);

        $expected = $this->expectedGidCids($font['usedgid'] + $other['usedgid']);
        $this->assertContains($gidC, $expected);
        $this->assertSame($expected, $this->getCompressedCids($block, $refs[0] ?? 0));
        $this->assertSame($expected, $this->getCompressedCids($block, $refs[1] ?? 0));
    }

    public function testCidSetBits(): void
    {
        $util = new OutUtilTestHarness();

        // CID 0 is always flagged
        $this->assertSame("\x80", $util->runCidSetBits([]));
        $this->assertSame("\x81", $util->runCidSetBits([7 => true]));
        $this->assertSame("\x80\x80", $util->runCidSetBits([8 => true]));
        $this->assertSame("\xC0", $util->runCidSetBits([1 => true, 9 => false]));
        $this->assertSame("\x80", $util->runCidSetBits([-1 => true, 65_536 => true]));

        $bits = $util->runCidSetBits([65_535 => true]);
        $this->assertSame(8192, \strlen($bits));
        $this->assertSame("\x80", $bits[0]);
        $this->assertSame("\x01", $bits[8191]);
    }
}
