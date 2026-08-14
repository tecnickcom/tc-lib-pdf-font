<?php

/**
 * CtgTableTest.php
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

use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * Covers the CIDToGIDMap artifact: loading it at encoding time and embedding it for the
 * composite fonts that address glyphs by codepoint rather than by glyph index.
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
class CtgTableTest extends TestUtil
{
    /**
     * Write a minimal TrueTypeUnicode definition pointing at the given ctg artifact.
     */
    private function writeDefinition(string $key, string $ctg): void
    {
        \file_put_contents(
            $this->getFontPath() . $key . '.json',
            '{"type":"TrueTypeUnicode","name":"'
            . $key
            . '","dw":600'
            . ',"desc":{"FontBBox":"[0 -200 1000 800]","Ascent":800,"Descent":-200}'
            . ',"cw":{"65":400},"ctg":"'
            . $ctg
            . '"}',
        );
    }

    /**
     * Build a CIDToGIDMap: 65536 big-endian 16-bit glyph indices.
     *
     * @param array<int, int> $map codepoint => glyph index
     */
    private function buildCtg(array $map): string
    {
        $table = \str_repeat("\x00", 131_072);
        foreach ($map as $ord => $gid) {
            $table[$ord * 2] = \chr($gid >> 8);
            $table[($ord * 2) + 1] = \chr($gid & 0xFF);
        }

        return $table;
    }

    /** @throws \Throwable */
    private function loadStack(string $key): Stack
    {
        $objnum = 1;
        $stack = new Stack(1);
        $stack->insert($objnum, $key, '', null, null, null, '', false);

        return $stack;
    }

    /** @throws \Throwable */
    public function testGidLookupReadsTheCompressedCtgArtifact(): void
    {
        $this->setupTest();
        $this->writeDefinition('ctgok', 'ctgok.ctg.z');
        \file_put_contents(
            $this->getFontPath() . 'ctgok.ctg.z',
            (string) \gzcompress($this->buildCtg([65 => 36, 0x4E00 => 4096])),
        );

        $stack = $this->loadStack('ctgok');

        $this->assertTrue($stack->isCurrentGidEncoded());
        $this->assertSame(36, $stack->getGidForOrd(65));
        $this->assertSame(4096, $stack->getGidForOrd(0x4E00));
        // an unmapped codepoint resolves to notdef
        $this->assertSame(0, $stack->getGidForOrd(66));
        // a second lookup is served from the in-memory table
        $this->assertSame(36, $stack->getGidForOrd(65));
    }

    /** @throws \Throwable */
    public function testGidLookupReadsAnUncompressedCtgArtifact(): void
    {
        $this->setupTest();
        $this->writeDefinition('ctgraw', 'ctgraw.ctg');
        \file_put_contents($this->getFontPath() . 'ctgraw.ctg', $this->buildCtg([65 => 7]));

        $this->assertSame(7, $this->loadStack('ctgraw')->getGidForOrd(65));
    }

    /**
     * A table shorter than 131072 bytes is padded, so codepoints past its end read as
     * notdef instead of running off the string.
     *
     * @throws \Throwable
     */
    public function testShortCtgArtifactIsPaddedToTheFullTable(): void
    {
        $this->setupTest();
        $this->writeDefinition('ctgshort', 'ctgshort.ctg');
        // only the first two entries are present
        \file_put_contents($this->getFontPath() . 'ctgshort.ctg', "\x00\x00\x00\x2A");

        $stack = $this->loadStack('ctgshort');

        $this->assertSame(42, $stack->getGidForOrd(1));
        $this->assertSame(0, $stack->getGidForOrd(0xFFFF));
    }

    /**
     * A table longer than 131072 bytes is cut: the extra bytes are addressed by no
     * codepoint, and the cached entry stays bounded by the documented table size.
     *
     * @throws \Throwable
     */
    public function testOversizedCtgArtifactIsTruncatedToTheTableSize(): void
    {
        $this->setupTest();
        $this->writeDefinition('ctglong', 'ctglong.ctg');
        \file_put_contents($this->getFontPath() . 'ctglong.ctg', $this->buildCtg([65 => 11, 0xFFFF => 22])
            . \str_repeat("\x7F", 4096));

        $stack = $this->loadStack('ctglong');

        $this->assertSame(11, $stack->getGidForOrd(65));
        // the last addressable entry still reads from the table, not from the tail
        $this->assertSame(22, $stack->getGidForOrd(0xFFFF));
    }

    /**
     * A font without a CIDToGIDMap has no glyph index to report: the documented notdef is
     * returned instead of an exception naming an empty file.
     *
     * @throws \Throwable
     */
    public function testGidLookupReturnsNotdefForAFontWithoutCtgArtifact(): void
    {
        $this->setupTest();
        \file_put_contents(
            $this->getFontPath() . 'noctg.json',
            '{"type":"TrueTypeUnicode","name":"noctg","dw":600'
            . ',"desc":{"FontBBox":"[0 -200 1000 800]","Ascent":800,"Descent":-200}'
            . ',"cw":{"65":400},"ctg":""}',
        );

        $stack = $this->loadStack('noctg');

        $this->assertFalse($stack->isCurrentGidEncoded());
        $this->assertSame(0, $stack->getGidForOrd(65));
    }

    /** @throws \Throwable */
    public function testGidLookupFailsWhenTheCtgArtifactIsMissing(): void
    {
        $this->setupTest();
        $this->writeDefinition('ctggone', 'ctggone.ctg.z');

        $stack = $this->loadStack('ctggone');

        $this->assertThrowsMessage(
            FontException::class,
            'Unable to locate the file',
            /** @throws \Throwable */
            static fn() => $stack->getGidForOrd(65),
        );
    }

    /** @throws \Throwable */
    public function testGidLookupFailsWhenTheCtgArtifactIsNotValidZlibData(): void
    {
        $this->setupTest();
        $this->writeDefinition('ctgbad', 'ctgbad.ctg.z');
        \file_put_contents($this->getFontPath() . 'ctgbad.ctg.z', 'not zlib data at all');

        $stack = $this->loadStack('ctgbad');

        $this->assertThrowsMessage(
            FontException::class,
            'Unable to uncompress font file',
            /** @throws \Throwable */
            static fn() => $stack->getGidForOrd(65),
        );
    }

    /** @throws \Throwable */
    public function testGidLookupFailsWhenTheCtgArtifactExpandsBeyondTheTableSize(): void
    {
        $this->setupTest();
        $this->writeDefinition('ctgbomb', 'ctgbomb.ctg.z');
        // the table has a fixed size of 131072 bytes, which bounds the expansion
        \file_put_contents(
            $this->getFontPath() . 'ctgbomb.ctg.z',
            (string) \gzcompress(\str_repeat("\x00", 4_000_000)),
        );

        $stack = $this->loadStack('ctgbomb');

        $this->assertThrowsMessage(
            FontException::class,
            'Unable to uncompress font file',
            /** @throws \Throwable */
            static fn() => $stack->getGidForOrd(65),
        );
    }

    /** @throws \Throwable */
    public function testSupplementaryPlaneCodepointsUseTheCtguMapNotTheTable(): void
    {
        $this->setupTest();
        \file_put_contents(
            $this->getFontPath() . 'ctgu.json',
            '{"type":"TrueTypeUnicode","name":"ctgu","dw":600'
            . ',"desc":{"FontBBox":"[0 -200 1000 800]","Ascent":800,"Descent":-200}'
            . ',"cw":{"65":400},"ctg":"ctgu.ctg","ctgu":{"66560":900}}',
        );
        \file_put_contents($this->getFontPath() . 'ctgu.ctg', $this->buildCtg([65 => 7]));

        $stack = $this->loadStack('ctgu');

        // above the BMP: read from the definition file, no table access at all
        $this->assertSame(900, $stack->getGidForOrd(66560));
        $this->assertSame(0, $stack->getGidForOrd(0x20000));
        // a negative codepoint is notdef
        $this->assertSame(0, $stack->getGidForOrd(-1));
    }

    /**
     * Write a definition and its own CIDToGIDMap artifact mapping 'A' to the given glyph.
     */
    private function writeCachedFont(string $key, int $gid): void
    {
        $this->writeDefinition($key, $key . '.ctg');
        \file_put_contents($this->getFontPath() . $key . '.ctg', $this->buildCtg([65 => $gid]));
    }

    /**
     * Return the cache keys of the tables held by a stack, oldest first.
     *
     * @return array<int, string>
     *
     * @throws \ReflectionException
     */
    private function cachedTableKeys(Stack $stack): array
    {
        /** @var mixed $value */
        $value = (new \ReflectionProperty($stack, 'ctgtable'))->getValue($stack);
        $this->assertIsArray($value);

        $keys = [];
        foreach (\array_keys($value) as $key) {
            $keys[] = (string) $key;
        }

        return $keys;
    }

    /**
     * Each table is 131072 bytes, so the cache drops the least recently used entries
     * instead of holding one table per font for the whole life of the document.
     *
     * @throws \Throwable
     */
    public function testTheTableCacheIsBoundedAndReloadsAnEvictedTable(): void
    {
        $this->setupTest();
        $objnum = 1;
        $stack = new Stack(1);
        $total = 9;
        for ($idx = 0; $idx < $total; ++$idx) {
            $key = 'ctgcache' . $idx;
            $this->writeCachedFont($key, $idx + 1);
            $stack->insert($objnum, $key, '', null, null, null, '', false);
            $this->assertSame($idx + 1, $stack->getGidForOrd(65));
        }

        $keys = $this->cachedTableKeys($stack);
        $this->assertCount(8, $keys);
        // the first table read is the one dropped
        $this->assertStringNotContainsString('ctgcache0.ctg', \implode(',', $keys));
        $this->assertStringContainsString('ctgcache8.ctg', \implode(',', $keys));

        // the dropped table is read again on demand
        $stack->insert($objnum, 'ctgcache0', '', null, null, null, '', false);
        $this->assertSame(1, $stack->getGidForOrd(65));
    }

    /**
     * A table used again is kept: the eviction follows the order of use, not the order
     * in which the fonts were loaded.
     *
     * @throws \Throwable
     */
    public function testAReusedTableIsNotTheOneEvicted(): void
    {
        $this->setupTest();
        $objnum = 1;
        $stack = new Stack(1);
        for ($idx = 0; $idx < 8; ++$idx) {
            $key = 'ctglru' . $idx;
            $this->writeCachedFont($key, $idx + 1);
            $stack->insert($objnum, $key, '', null, null, null, '', false);
            $this->assertSame($idx + 1, $stack->getGidForOrd(65));
        }

        // touch the oldest entry, then load one more font
        $stack->insert($objnum, 'ctglru0', '', null, null, null, '', false);
        $this->assertSame(1, $stack->getGidForOrd(65));
        $this->writeCachedFont('ctglru8', 9);
        $stack->insert($objnum, 'ctglru8', '', null, null, null, '', false);
        $this->assertSame(9, $stack->getGidForOrd(65));

        $keys = \implode(',', $this->cachedTableKeys($stack));
        $this->assertStringContainsString('ctglru0.ctg', $keys);
        $this->assertStringNotContainsString('ctglru1.ctg', $keys);
    }

    /** @throws \Throwable */
    public function testAddUsedGidRejectsAnUnknownFontKey(): void
    {
        $this->setupTest();
        $this->writeDefinition('ctgok2', 'ctgok2.ctg');
        \file_put_contents($this->getFontPath() . 'ctgok2.ctg', $this->buildCtg([65 => 7]));

        $stack = $this->loadStack('ctgok2');

        $this->assertThrowsMessage(
            FontException::class,
            'has not been loaded',
            /** @throws \Throwable */
            static fn() => $stack->addUsedGid('nosuchfont', 1, 65),
        );
    }

    // -------------------------------------------------------------------------
    // embedding the artifact for a codepoint-addressed composite font
    // -------------------------------------------------------------------------

    /**
     * A definition that carries a ctg artifact but is not GID encoded (the layout written
     * by versions before 4.0) embeds the CIDToGIDMap as its own stream object.
     *
     * @return TFontData
     */
    private function legacyCompositeFont(string $ctg): array
    {
        /** @var TFontData $font */
        $font = [
            'cbbox' => [],
            'compress' => false,
            'cidinfo' => ['Ordering' => 'Identity', 'Registry' => 'Adobe', 'Supplement' => 0, 'uni2cid' => []],
            'ctg' => $ctg,
            'ctgdata' => [],
            'cw' => [65 => 400, 66 => 500],
            'desc' => [
                'Ascent' => 800,
                'AvgWidth' => 600,
                'CapHeight' => 700,
                'Descent' => -200,
                'Flags' => 32,
                'FontBBox' => '[0 -200 1000 800]',
                'ItalicAngle' => 0,
                'Leading' => 0,
                'MaxWidth' => 1000,
                'MissingWidth' => 600,
                'StemH' => 0,
                'StemV' => 70,
                'XHeight' => 500,
            ],
            'diff' => '',
            'diff_n' => 0,
            'dir' => $this->getFontPath(),
            'dw' => 600,
            'enc' => 'Identity-H',
            'file' => '',
            'file_n' => 0,
            'gidenc' => false,
            'i' => 1,
            'key' => 'legacy',
            'n' => 5,
            'name' => 'legacy',
            'subset' => false,
            'subsetchars' => [],
            'type' => 'TrueTypeUnicode',
            'up' => -100,
            'usedgid' => [],
            'ut' => 50,
        ];

        return $font;
    }

    /** @throws \Throwable */
    private function runOutput(string $ctg): string
    {
        $encryptClass = new \ReflectionClass(\Com\Tecnick\Pdf\Encrypt\Encrypt::class);
        $encrypt = $encryptClass->newInstanceWithoutConstructor();
        \assert($encrypt instanceof \Com\Tecnick\Pdf\Encrypt\Encrypt, 'the Encrypt stub must be usable');

        $output = new \Com\Tecnick\Pdf\Font\Output(['legacy' => $this->legacyCompositeFont($ctg)], 1, $encrypt, null);

        return $output->getFontsBlock();
    }

    /** @throws \Throwable */
    public function testCompressedCtgArtifactIsEmbeddedWithAFlateFilter(): void
    {
        $this->setupTest();
        \file_put_contents($this->getFontPath() . 'legacy.ctg.z', (string) \gzcompress($this->buildCtg([65 => 36])));

        $block = $this->runOutput('legacy.ctg.z');

        // the map is referenced from the CIDFont dictionary and emitted as its own object
        $this->assertStringContainsString('/CIDToGIDMap ', $block);
        $this->assertStringNotContainsString('/CIDToGIDMap /Identity', $block);
        $this->assertStringContainsString('/Filter /FlateDecode', $block);
        // widths are keyed by codepoint here, not by glyph index
        $this->assertStringContainsString('/W [ 65 [ 400 500 ] ]', $block);
    }

    /** @throws \Throwable */
    public function testUncompressedCtgArtifactIsEmbeddedWithoutAFilter(): void
    {
        $this->setupTest();
        \file_put_contents($this->getFontPath() . 'legacy.ctg', $this->buildCtg([65 => 36]));

        $block = $this->runOutput('legacy.ctg');

        $this->assertStringContainsString('/CIDToGIDMap ', $block);
        $this->assertStringNotContainsString('/Filter /FlateDecode', $block);
    }

    public function testEmbeddingFailsWhenTheCtgArtifactIsMissing(): void
    {
        $this->setupTest();

        $this->assertThrowsMessage(
            FontException::class,
            'Unable to locate the file',
            /** @throws \Throwable */
            fn() => $this->runOutput('legacy.ctg.z'),
        );
    }

    /**
     * FontPaths::findFontFile() searches the current working directory first, which the
     * file helper does not trust: the artifact is located there but refused.
     */
    public function testEmbeddingFailsWhenTheCtgArtifactIsOutsideTheTrustedRoots(): void
    {
        $this->setupTest();
        $planted = (string) \getcwd() . '/legacy.ctg.z';
        \file_put_contents($planted, (string) \gzcompress($this->buildCtg([65 => 36])));

        try {
            $this->assertThrowsMessage(
                FontException::class,
                'Unable to read font file',
                /** @throws \Throwable */
                fn() => $this->runOutput('legacy.ctg.z'),
            );
        } finally {
            \unlink($planted);
        }
    }
}
