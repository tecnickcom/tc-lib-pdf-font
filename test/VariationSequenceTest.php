<?php

/**
 * VariationSequenceTest.php
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

use Com\Tecnick\File\Byte;
use Com\Tecnick\File\Exception as FileException;
use Com\Tecnick\File\File as ObjFile;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Import\TrueType;
use Com\Tecnick\Pdf\Font\Load;
use Com\Tecnick\Pdf\Font\Stack;
use Com\Tecnick\Pdf\Font\Subset;

/**
 * Tests for the Unicode Variation Sequences (cmap format 14)
 *
 * The fixture font test/fixtures/ivs/ivstest.ttf has the glyphs .notdef (0), space (1),
 * A (2), A.vs1 (3), A.vs2 (4), B (5), uni908A (6) and uni908A.vs1 (7), and the sequences
 * A+FE00 (default), A+FE01 and B+FE01 (A.vs1), A+FE02 (A.vs2), U+908A+E0100 (default)
 * and U+908A+E0101 (uni908A.vs1).
 *
 * @since     2026-11-02
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class VariationSequenceTest extends TestUtil
{
    protected function getFixturePath(): string
    {
        return __DIR__ . '/fixtures/ivs/ivstest.ttf';
    }

    /**
     * Returns a font stack with the fixture font loaded as the current font.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    protected function getStack(float $spacing = 0.0, bool $subset = true): Stack
    {
        $this->setupTest();
        $objnum = 1;
        $stack = new Stack(1.0, $subset, true, false);
        new \Com\Tecnick\Pdf\Font\Import($this->getFixturePath());
        $stack->insert($objnum, 'ivstest', '', 10, $spacing);
        return $stack;
    }

    /**
     * Returns the fonts block written with the CIDSet streams.
     *
     * @throws FileException
     * @throws FontException
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     */
    protected function getFontsBlock(Stack $stack): string
    {
        $objnum = 100;
        $out = new \Com\Tecnick\Pdf\Font\Output(
            $stack->getFonts(),
            $objnum,
            new \Com\Tecnick\Pdf\Encrypt\Encrypt(false),
            null,
            null,
            true,
        );
        return $out->getFontsBlock();
    }

    /**
     * Returns the uncompressed data of the stream of the given object.
     */
    protected function getStreamData(string $block, int $objnum): string
    {
        $objpos = \strpos($block, $objnum . " 0 obj\n");
        $this->assertNotFalse($objpos);
        $start = \strpos($block, " stream\n", $objpos);
        $this->assertNotFalse($start);
        $start += 8;
        $end = \strpos($block, "\nendstream", $start);
        $this->assertNotFalse($end);

        $data = \gzuncompress(\substr($block, $start, $end - $start));
        $this->assertNotFalse($data);
        return $data;
    }

    /**
     * Returns the object number referenced by the given dictionary key.
     */
    protected function getRef(string $block, string $name): int
    {
        $matches = [];
        $this->assertSame(1, \preg_match('#/' . $name . ' (\d+) 0 R#', $block, $matches));
        return (int) ($matches[1] ?? 0);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportStoresTheSequencesAndTheirGlyphWidths(): void
    {
        $this->setupTest();
        new \Com\Tecnick\Pdf\Font\Import($this->getFixturePath());
        $def = $this->decodeDefinition($this->getFontPath() . 'ivstest.json');

        $this->assertSame(8, $this->intMember($def, 'numGlyphs'));
        $this->assertSame(
            [
                65 => [0xFE00 => 2, 0xFE01 => 3, 0xFE02 => 4],
                66 => [0xFE01 => 3],
                0x908A => [0xE_0100 => 6, 0xE_0101 => 7],
            ],
            \array_map($this->intMap(...), $this->arrayMember($def, 'uvs')),
        );
        $this->assertSame(
            [2 => 600, 3 => 700, 4 => 800, 6 => 1000, 7 => 900],
            $this->intMap($this->arrayMember($def, 'gw')),
        );
    }

    /**
     * A font with no format 14 subtable keeps a definition file without the sequence members.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportWithoutSequencesWritesNoSequenceMember(): void
    {
        $this->setupTest();
        new \Com\Tecnick\Pdf\Font\Import(
            \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/freefont/FreeSans.ttf',
        );
        $json = (string) \file_get_contents($this->getFontPath() . 'freesans.json');

        $this->assertStringNotContainsString('"uvs"', $json);
        $this->assertStringNotContainsString('"gw"', $json);
        $this->assertStringNotContainsString('"numGlyphs"', $json);
    }

    public function testIsVariationSelector(): void
    {
        foreach ([0x180B, 0x180D, 0x180F, 0xFE00, 0xFE0F, 0xE_0100, 0xE_01EF] as $ord) {
            $this->assertTrue(Stack::isVariationSelector($ord), \sprintf('U+%04X', $ord));
        }

        foreach ([-1, 0x41, 0x180A, 0x180E, 0x1810, 0xFDFF, 0xFE10, 0xE_00FF, 0xE_01F0] as $ord) {
            $this->assertFalse(Stack::isVariationSelector($ord), \sprintf('U+%04X', $ord));
        }
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testGetVariationGid(): void
    {
        $stack = $this->getStack();

        $this->assertSame(2, $stack->getVariationGid(0x41, 0xFE00));
        $this->assertSame(3, $stack->getVariationGid(0x41, 0xFE01));
        $this->assertSame(3, $stack->getVariationGid(0x42, 0xFE01));
        $this->assertSame(7, $stack->getVariationGid(0x908A, 0xE_0101));
        $this->assertSame(0, $stack->getVariationGid(0x42, 0xFE00));
        $this->assertSame(0, $stack->getVariationGid(0x20, 0xFE00));
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testSequencesAreEncodedWithTheirGlyphs(): void
    {
        $stack = $this->getStack();

        // A+FE01, A+FE02, U+908A+E0101: the variant glyphs
        $this->assertSame(
            '000300040007',
            \bin2hex($stack->ordArrToGidStr([
                0x41,
                0xFE01,
                0x41,
                0xFE02,
                0x908A,
                0xE_0101,
            ])),
        );
        // a sequence the font does not map shows the base glyph, a lone selector nothing
        $this->assertSame('00050002', \bin2hex($stack->ordArrToGidStr([0xFE01, 0x42, 0xFE0F, 0xE_0100, 0x41])));

        $font = $stack->getFont('ivstest');
        $this->assertSame([3 => 0x41, 4 => 0x41, 7 => 0x908A, 5 => 0x42, 2 => 0x41], $font['usedgid']);
        $this->assertSame([3 => [0x41, 0xFE01], 4 => [0x41, 0xFE02], 7 => [0x908A, 0xE_0101]], $font['usedseq']);
        $this->assertSame([], $font['usedalias']);
        // the selectors are not glyphs of the subset
        $this->assertArrayHasKey(0x908A, $font['subsetchars']);
        foreach ([0xFE01, 0xFE02, 0xFE0F, 0xE_0100, 0xE_0101] as $ord) {
            $this->assertArrayNotHasKey($ord, $font['subsetchars']);
        }
    }

    /**
     * A glyph shown for two texts gets an alias code for the second one when either text
     * is a sequence.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAGlyphSharedByTwoTextsGetsAnAliasCode(): void
    {
        $stack = $this->getStack();

        // A, A+FE01, B+FE01 (same glyph as A+FE01), A+FE00 (same glyph as A), again B+FE01
        $this->assertSame(
            '00020003000800090008',
            \bin2hex($stack->ordArrToGidStr([
                0x41,
                0x41,
                0xFE01,
                0x42,
                0xFE01,
                0x41,
                0xFE00,
                0x42,
                0xFE01,
            ])),
        );

        $font = $stack->getFont('ivstest');
        $this->assertSame([8 => 3, 9 => 2], $font['usedalias']);
        $this->assertSame([3 => [0x41, 0xFE01], 8 => [0x42, 0xFE01], 9 => [0x41, 0xFE00]], $font['usedseq']);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     * @throws \Throwable
     */
    public function testAddUsedGlyph(): void
    {
        $stack = $this->getStack();

        // two single codepoints sharing a glyph keep the first one
        $this->assertSame(2, $stack->addUsedGlyph('ivstest', 2, [0x41]));
        $this->assertSame(2, $stack->addUsedGlyph('ivstest', 2, [0x61]));
        $this->assertSame(2, $stack->addUsedGlyph('ivstest', 2, [0x41]));
        $this->assertSame(8, $stack->addUsedGlyph('ivstest', 2, [0x41, 0xFE00]));
        $this->assertSame(8, $stack->addUsedGlyph('ivstest', 2, [0x41, 0xFE00]));
        $this->assertSame(9, $stack->addUsedGlyph('ivstest', 2, [0x61, 0xFE00]));

        $this->assertThrowsMessage(
            FontException::class,
            'is empty',
            /** @throws \Throwable */
            static fn() => $stack->addUsedGlyph('ivstest', 2, []),
        );
        $this->assertThrowsMessage(
            FontException::class,
            'outside',
            /** @throws \Throwable */
            static fn() => $stack->addUsedGlyph('ivstest', 0x1_0000, [0x41]),
        );
        $this->assertThrowsMessage(
            FontException::class,
            'not been loaded',
            /** @throws \Throwable */
            static fn() => $stack->addUsedGlyph('missing', 2, [0x41]),
        );
    }

    /**
     * Without a glyph count, or past the last 16-bit code, a shared glyph keeps its first text.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAliasCodesNeedRoomInTheCodeSpace(): void
    {
        $stack = $this->getStack();
        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Font\Buffer::class, 'font');

        /** @var array<string, array<string, mixed>> $fonts */
        $fonts = $prop->getValue($stack);
        $fonts['ivstest']['numGlyphs'] = 0;
        $prop->setValue($stack, $fonts);
        $this->assertSame(2, $stack->addUsedGlyph('ivstest', 2, [0x41]));
        $this->assertSame(2, $stack->addUsedGlyph('ivstest', 2, [0x41, 0xFE00]));

        $fonts['ivstest']['numGlyphs'] = 0xFFFF;
        $prop->setValue($stack, $fonts);
        $this->assertSame(2, $stack->addUsedGlyph('ivstest', 2, [0x41]));
        $this->assertSame(0xFFFF, $stack->addUsedGlyph('ivstest', 2, [0x41, 0xFE00]));
        $this->assertSame(2, $stack->addUsedGlyph('ivstest', 2, [0x41, 0xFE01]));
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testSelectorsTakeNoSpace(): void
    {
        $stack = $this->getStack(1.0);

        $this->assertSame(0.0, $stack->getCharWidth(0xFE01));
        $this->assertSame(0.0, $stack->getCharWidth(0xE_0101));
        $this->assertTrue($stack->isCharDefined(0xFE01));
        $this->assertFalse($stack->isCharDefined(0x43));

        // 10 pt: A = 6, A.vs1 = 7, uni908A.vs1 = 9, B with an unmapped selector = 6.1
        $widths = $stack->getOrdArrCharWidths([0x41, 0xFE01, 0x41, 0x908A, 0xE_0101, 0x42, 0xFE0F]);
        $this->assertCount(7, $widths);
        foreach ([7.0, 0.0, 6.0, 9.0, 0.0, 6.1, 0.0] as $idx => $expected) {
            $this->bcAssertEqualsWithDelta($expected, $widths[$idx] ?? -1.0);
        }

        // three glyphs, so two character spacings of 1
        $dims = $stack->getOrdArrDims([0x41, 0xFE01, 0x41, 0x908A, 0xE_0101]);
        $this->bcAssertEqualsWithDelta(7.0 + 6.0 + 9.0 + 2.0, $dims['totwidth']);
        $this->assertSame(5, $dims['chars']);

        $dims = $stack->getOrdArrDims([0x41, 0xFE01, 0x20, 0x41]);
        $split = $dims['split'][0] ?? null;
        $this->assertIsArray($split);
        // A.vs1 before the space: one glyph, no character spacing
        $this->bcAssertEqualsWithDelta(7.0, $split['totwidth']);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     */
    public function testOutputMapsTheSequencesAndTheAliasCodes(): void
    {
        $stack = $this->getStack();
        $stack->ordArrToGidStr([0x41, 0x41, 0xFE01, 0x42, 0xFE01, 0x41, 0xFE00, 0x908A, 0xE_0101]);
        $block = $this->getFontsBlock($stack);

        $cmap = $this->getStreamData($block, $this->getRef($block, 'ToUnicode'));
        $this->assertStringContainsString(
            "5 beginbfchar\n<0002> <0041>\n<0003> <0041fe01>\n<0007> <908adb40dd01>\n"
            . "<0008> <0042fe01>\n<0009> <0041fe00>\nendbfchar",
            $cmap,
        );

        // alias codes take the width of their glyph
        $this->assertStringContainsString('/W [ 2 [ 600 700 ] 7 [ 900 700 600 ] ]', $block);

        $ctgobj = $this->getRef($block, 'CIDToGIDMap');
        $this->assertStringContainsString(
            $ctgobj . " 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 2 >>",
            $block,
        );
        $ctg = $this->undoPngUp($this->getStreamData($block, $ctgobj));
        $this->assertSame('0000000100020003000400050006000700030002', \bin2hex($ctg));
        $this->assertStringNotContainsString('/CIDToGIDMap /Identity', $block);

        // CIDs 0, 2, 3, 7, 8 and 9
        $this->assertSame('b1c0', \bin2hex($this->getStreamData($block, $this->getRef($block, 'CIDSet'))));
    }

    /**
     * Returns the data of a stream encoded with the PNG Up predictor over rows of two bytes.
     *
     * @param string $data Predicted rows, each one prefixed with its filter type.
     */
    protected function undoPngUp(string $data): string
    {
        $out = '';
        $prev = "\0\0";
        foreach (\str_split($data, 3) as $row) {
            $this->assertSame("\x02", $row[0]);
            $cur =
                \chr((\ord($row[1] ?? "\0") + \ord($prev[0])) & 0xFF)
                . \chr((\ord($row[2] ?? "\0") + \ord($prev[1])) & 0xFF);
            $out .= $cur;
            $prev = $cur;
        }

        return $out;
    }

    /**
     * An uncompressed map lists the glyph indices as they are.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     */
    public function testUncompressedAliasMapHasNoPredictor(): void
    {
        $stack = $this->getStack();
        $stack->ordArrToGidStr([0x41, 0x41, 0xFE00]);
        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Font\Buffer::class, 'font');
        /** @var array<string, array<string, mixed>> $fonts */
        $fonts = $prop->getValue($stack);
        $fonts['ivstest']['compress'] = false;
        $prop->setValue($stack, $fonts);
        $block = $this->getFontsBlock($stack);

        $ctgobj = $this->getRef($block, 'CIDToGIDMap');
        $expected = \pack('n*', 0, 1, 2, 3, 4, 5, 6, 7, 2);
        $this->assertStringContainsString(
            $ctgobj . " 0 obj\n<< /Length 18 >> stream\n" . $expected . "\nendstream",
            $block,
        );
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     */
    public function testOutputWithoutAliasCodesKeepsTheIdentityMap(): void
    {
        $stack = $this->getStack();
        $stack->ordArrToGidStr([0x41, 0xFE01, 0x42]);
        $block = $this->getFontsBlock($stack);

        $this->assertStringContainsString('/CIDToGIDMap /Identity', $block);
        // the CIDSet stream follows the descriptor
        $desc = $this->getRef($block, 'FontDescriptor');
        $this->assertSame($desc + 1, $this->getRef($block, 'CIDSet'));
    }

    /**
     * The glyph of a sequence is kept in the subset, although no subset character maps to it.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testSubsetKeepsTheGivenGlyphs(): void
    {
        $stack = $this->getStack();
        $font = (string) \file_get_contents($this->getFixturePath());
        $fdt = $stack->getFont('ivstest');
        $helper = new ObjFile(allowedPaths: [\dirname($this->getFixturePath())]);

        $sub = new Subset($font, $fdt, $helper, [0x41 => true], [3 => true, 7 => true, 99 => true, 4 => false]);
        /** @var array<int, bool> $glyphs */
        $glyphs = (new \ReflectionProperty(Subset::class, 'subglyphs'))->getValue($sub);
        $this->assertSame([0, 2, 3, 7], \array_keys($glyphs));
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     */
    public function testEmbeddedSubsetHoldsTheVariantGlyphs(): void
    {
        $stack = $this->getStack();
        $stack->ordArrToGidStr([0x41, 0xFE02, 0x908A, 0xE_0101]);
        $block = $this->getFontsBlock($stack);
        $program = $this->getStreamData($block, $this->getRef($block, 'FontFile2'));

        $this->assertStringContainsString('/BaseFont /AAAAAB+IVSTest-Regular', $block);
        $loca = $this->getLoca($program);
        $this->assertCount(9, $loca);
        // the glyphs of A.vs2 (4) and uni908A.vs1 (7) have outlines, the unused A.vs1 (3) none
        $this->assertGreaterThan(0, ($loca[5] ?? 0) - ($loca[4] ?? 0));
        $this->assertGreaterThan(0, ($loca[8] ?? 0) - ($loca[7] ?? 0));
        $this->assertSame(0, ($loca[4] ?? 0) - ($loca[3] ?? 0));
    }

    /**
     * Returns the glyph offsets of the loca table of a font program.
     *
     * @return array<int, int>
     */
    protected function getLoca(string $program): array
    {
        $numTables = (\ord($program[4] ?? "\0") << 8) | \ord($program[5] ?? "\0");
        $short = false;
        $loca = '';
        for ($idx = 0; $idx < $numTables; ++$idx) {
            $rec = 12 + ($idx * 16);
            $tag = \substr($program, $rec, 4);
            /** @var array{1: int, 2: int} $pos */
            $pos = \unpack('N2', $program, $rec + 8);
            if ($tag === 'head') {
                $short = \substr($program, $pos[1] + 50, 2) === "\0\0";
            } elseif ($tag === 'loca') {
                $loca = \substr($program, $pos[1], $pos[2]);
            }
        }

        $this->assertNotSame('', $loca);
        /** @var array<int, int> $offsets */
        $unpacked = \unpack($short ? 'n*' : 'N*', $loca);
        $offsets = \array_values($unpacked === false ? [] : $unpacked);
        return $short ? \array_map(static fn(int $off): int => $off * 2, $offsets) : $offsets;
    }

    /**
     * Build a TrueType importer over a synthetic cmap table holding a format 14 subtable.
     *
     * @param string          $subtable   Subtable bytes, placed at offset 0 of the cmap table.
     * @param int             $cmapLength Declared length of the cmap table.
     * @param array<int, int> $ctgdata    Character map of the font.
     */
    protected function buildImporter(string $subtable, int $cmapLength, array $ctgdata = []): TrueType
    {
        $font = $subtable . \str_repeat("\x77", 16);
        $instance = (new \ReflectionClass(TrueType::class))->newInstanceWithoutConstructor();

        try {
            $byte = new Byte($font);
        } catch (\RangeException $exception) {
            $this->fail($exception->getMessage());
        }

        $fdt = \array_replace(Load::DEFAULT_DATA, [
            'ctgdata' => $ctgdata,
            'numGlyphs' => 10,
            'tot_num_glyphs' => 11,
            'table' => ['cmap' => ['checkSum' => 0, 'data' => '', 'offset' => 0, 'length' => $cmapLength]],
        ]);

        (new \ReflectionProperty(TrueType::class, 'font'))->setValue($instance, $font);
        (new \ReflectionProperty(TrueType::class, 'fdt'))->setValue($instance, $fdt);
        (new \ReflectionProperty(TrueType::class, 'fbyte'))->setValue($instance, $byte);

        return $instance;
    }

    /**
     * @return array<int, array<int, int>>
     */
    protected function readSequences(TrueType $instance): array
    {
        (new \ReflectionMethod(TrueType::class, 'processFormat14'))->invoke($instance, 0);

        return $instance->getFontMetrics()['uvs'];
    }

    public function testFormat14ReadsBothTableKinds(): void
    {
        $subtable =
            "\x00\x0E" // format
            . "\x00\x00\x00\x00" // length (unused)
            . "\x00\x00\x00\x02" // two records
            . "\x00\xFE\x00\x00\x00\x00\x20\x00\x00\x00\x00" // FE00: default UVS at 32
            . "\x0E\x01\x01\x00\x00\x00\x00\x00\x00\x00\x28" // E0101: non-default UVS at 40
            . "\x00\x00\x00\x01" // one range
            . "\x00\x00\x41\x01" // U+0041..U+0042
            . "\x00\x00\x00\x02" // two mappings
            . "\x00\x00\x41\x00\x09" // U+0041 -> glyph 9
            . "\x00\x00\x43\x00\x0A"; // U+0043 -> glyph 10, outside the glyph count
        $instance = $this->buildImporter($subtable, \strlen($subtable), [0x41 => 3]);

        // U+0042 has no default glyph
        $this->assertSame([0x41 => [0xFE00 => 3, 0xE_0101 => 9]], $this->readSequences($instance));
    }

    public function testFormat14StopsAtTheEndOfTheCmapTable(): void
    {
        $subtable =
            "\x00\x0E" // format
            . "\x00\x00\x00\x00" // length (unused)
            . "\x00\x00\x00\x09" // nine records declared
            . "\x0E\x01\x00\x00\x00\x00\x00\x00\x00\x00\x15" // E0100: non-default UVS at 21
            . "\x00\x00\x00\x05" // five mappings declared
            . "\x00\x00\x41\x00\x02"; // one mapping in the table
        $instance = $this->buildImporter($subtable, \strlen($subtable));

        $this->assertSame([0x41 => [0xE_0100 => 2]], $this->readSequences($instance));
    }

    public function testFormat14IgnoresATableOfAnotherFormat(): void
    {
        $instance = $this->buildImporter("\x00\x0C\x00\x00\x00\x00\x00\x00\x00\x01", 10);

        $this->assertSame([], $this->readSequences($instance));
    }

    public function testFormat14RejectsTooManySequences(): void
    {
        $subtable =
            "\x00\x0E" // format
            . "\x00\x00\x00\x00" // length (unused)
            . "\x00\x00\x00\x01" // one record
            . "\x00\xFE\x00\x00\x00\x00\x15\x00\x00\x00\x00" // FE00: default UVS at 21
            . "\x00\x00\x00\x03" // three ranges of 256 characters exceed the minimum budget
            . "\x00\x10\x00\xFF\x00\x20\x00\xFF\x00\x30\x00\xFF";
        $instance = $this->buildImporter($subtable, \strlen($subtable));

        $this->assertThrowsMessage(
            FontException::class,
            'too many variation sequences',
            function () use ($instance): void {
                $this->readSequences($instance);
            },
        );
    }

    public function testDefinitionReadsTheSequenceMembers(): void
    {
        $data = \Com\Tecnick\Pdf\Font\Definition::normalize([
            'numGlyphs' => '8',
            'uvs' => ['65' => ['65025' => 3, 'x' => 4], '66' => 'bad', 'y' => ['1' => 1], '67' => []],
            'gw' => ['3' => 700, 'z' => 1],
            'usedseq' => ['3' => [65, '65025', 'x'], '4' => 'bad', '5' => []],
            'usedalias' => ['8' => 3],
        ]);

        $this->assertSame(8, $data['numGlyphs'] ?? null);
        $this->assertSame([65 => [65025 => 3]], $data['uvs'] ?? null);
        $this->assertSame([3 => 700], $data['gw'] ?? null);
        $this->assertSame([3 => [65, 65025]], $data['usedseq'] ?? null);
        $this->assertSame([8 => 3], $data['usedalias'] ?? null);
    }
}
