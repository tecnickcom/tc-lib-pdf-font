<?php

/**
 * SubsetMalformedFontTest.php
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

use Com\Tecnick\File\File as ObjFile;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\FontPaths;
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Subset;

/**
 * Subsetting of a truncated or corrupt font program.
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
class SubsetMalformedFontTest extends TestUtil
{
    private const MIRROR = 'util/vendor/tecnickcom/tc-font-mirror/';

    /**
     * The library default font data array, used as the base of the fixtures.
     *
     * @return TFontData
     */
    private function getFontTemplate(): array
    {
        $ref = new \ReflectionClass(Subset::class);
        /** @var TFontData $value */
        $value = $ref->getProperty('fdt')->getValue($ref->newInstanceWithoutConstructor());

        return $value;
    }

    /**
     * Load the definition and the raw program of the bundled FreeSans font.
     *
     * @return array{0: TFontData, 1: string}
     *
     * @throws \Throwable
     */
    private function importFreeSans(): array
    {
        $this->setupTest();
        $indir = \dirname(__DIR__) . '/' . self::MIRROR . 'freefont/';
        $outdir = $this->getFontPath();
        new Import($indir . 'FreeSans.ttf', $outdir);

        $definition = \file_get_contents($outdir . 'freesans.json');
        $this->assertIsString($definition);
        /** @var array<array-key, mixed> $decoded */
        $decoded = \json_decode($definition, true);

        $program = \file_get_contents($indir . 'FreeSans.ttf');
        $this->assertIsString($program);

        /** @var TFontData $fdt */
        $fdt = \array_replace_recursive($this->getFontTemplate(), $decoded);

        return [$fdt, $program];
    }

    /**
     * A truncated font program leaves the table directory pointing past the end of the
     * file, which the bounds check rejects before any parsing takes place.
     *
     * @throws \Throwable
     */
    public function testSubsetRejectsATruncatedFontProgram(): void
    {
        [$fdt, $program] = $this->importFreeSans();
        $truncated = \substr($program, 0, (int) (\strlen($program) / 2));

        $fileHelper = new ObjFile(allowedPaths: FontPaths::buildAllowedPaths());

        $this->assertThrowsMessage(
            FontException::class,
            'Font table out of bounds',
            /** @throws \Throwable */
            static fn() => new Subset($truncated, $fdt, $fileHelper, [65 => true]),
        );
    }

    /**
     * Read a big-endian uint16 ('n') or uint32 ('N') out of a font program.
     */
    private static function readBigEndian(string $data, int $offset, string $format): int
    {
        $size = $format === 'N' ? 4 : 2;
        $value = \unpack($format . 'value', \substr($data, $offset, $size));

        return $value === false ? 0 : (int) ($value['value'] ?? 0);
    }

    /**
     * Replace the cmap table of a font program with a subtable truncated inside a field the
     * reader consumes whole.
     *
     * The new table is appended to the file and the directory record is repointed at it, so
     * the table itself is in bounds and the directory bounds check passes: the byte reader
     * is the one that runs past the end of the string.
     *
     * A format 8 subtable carries an 8192 byte is32 array, whose last byte is read to reject
     * a subtable truncated inside it. The appended table stops right after the header.
     */
    public static function corruptCmapSubtableOffsets(string $program): string
    {
        // format, then the reserved, length and language fields the reader skips
        $subtable = \pack('nnNN', 8, 0, 0, 0);
        $cmapTable = \pack('nn', 0, 1) . \pack('nnN', 3, 1, 12) . $subtable;

        // the table must start on a four byte boundary, as the sfnt specification requires
        $corrupt = $program . \str_repeat("\x00", (4 - (\strlen($program) % 4)) % 4);
        $newOffset = \strlen($corrupt);
        $corrupt .= $cmapTable;

        $tables = self::readBigEndian($program, 4, 'n');
        for ($idx = 0; $idx < $tables; ++$idx) {
            $record = 12 + ($idx * 16);
            if (\substr($program, $record, 4) !== 'cmap') {
                continue;
            }

            $corrupt = \substr_replace($corrupt, \pack('N', $newOffset), $record + 8, 4);
            $corrupt = \substr_replace($corrupt, \pack('N', \strlen($cmapTable)), $record + 12, 4);
        }

        return $corrupt;
    }

    /**
     * A table record that is in bounds but points the character map elsewhere makes the
     * byte reader run past the end of the string: the RangeException it raises is reported
     * as a FontException, the exception type this library contracts.
     *
     * @throws \Throwable
     */
    public function testSubsetReportsAnOutOfBoundsReadAsAFontError(): void
    {
        [$fdt, $program] = $this->importFreeSans();
        $corrupt = self::corruptCmapSubtableOffsets($program);
        $this->assertNotSame($program, $corrupt, 'the fixture must have been altered');

        $fileHelper = new ObjFile(allowedPaths: FontPaths::buildAllowedPaths());

        $this->assertThrowsMessage(
            FontException::class,
            'Malformed font program',
            /** @throws \Throwable */
            static fn() => new Subset($corrupt, $fdt, $fileHelper, [65 => true]),
        );
    }

    /**
     * The table directory declares the real length of each table; only the physical bytes
     * are padded to the next four byte boundary, so 'head' is emitted as the 54 bytes the
     * specification fixes for it.
     *
     * @throws \Throwable
     */
    public function testSubsetDeclaresTheRealLengthOfEveryTable(): void
    {
        [$fdt, $program] = $this->importFreeSans();

        $fileHelper = new ObjFile(allowedPaths: FontPaths::buildAllowedPaths());
        $subset = (new Subset($program, $fdt, $fileHelper, [65 => true]))->getSubsetFont();

        $header = \unpack('nnum', \substr($subset, 4, 2));
        $this->assertIsArray($header);
        $numTables = (int) ($header['num'] ?? 0);
        $this->assertGreaterThan(0, $numTables);

        $lengths = [];
        for ($idx = 0; $idx < $numTables; ++$idx) {
            $record = \substr($subset, 12 + ($idx * 16), 16);
            $fields = \unpack('Noffset/Nlength', \substr($record, 8, 8));
            $this->assertIsArray($fields);
            $lengths[\substr($record, 0, 4)] = (int) ($fields['length'] ?? 0);
            // every table still starts on a four byte boundary
            $this->assertSame(0, (int) ($fields['offset'] ?? 0) % 4);
        }

        $this->assertSame(54, $lengths['head'] ?? null, 'the head table is exactly 54 bytes');
    }

    /**
     * Subsetting reuses the TrueType reader over an already imported font, and the mode is
     * passed explicitly, so no artifact is written even when desc.MaxWidth is zero.
     *
     * @throws \Throwable
     */
    public function testSubsettingAFontWithoutMaxWidthWritesNothing(): void
    {
        [$fdt, $program] = $this->importFreeSans();
        $fdt['desc']['MaxWidth'] = 0;

        $dir = $this->getFontPath();
        $before = \scandir($dir);
        $this->assertIsArray($before);

        $fileHelper = new ObjFile(allowedPaths: FontPaths::buildAllowedPaths());
        $subset = new Subset($program, $fdt, $fileHelper, [65 => true]);
        $this->assertNotSame('', $subset->getSubsetFont());

        $after = \scandir($dir);
        $this->assertIsArray($after);
        $this->assertSame($before, $after, 'subsetting must not write to the font directory');
    }

    /**
     * The walk of a MORE_COMPONENTS chain stops at the end of its own glyph, so a corrupt
     * chain cannot collect the bytes of the neighbouring tables as component glyph indexes.
     *
     * @throws \Throwable
     */
    public function testCompositeGlyphWalkStopsAtTheEndOfTheGlyph(): void
    {
        [$fdt, $program] = $this->importFreeSans();

        // point the glyf table at the last four bytes of the file, so that every glyph
        // offset resolves to the very end of it
        $header = \unpack('nnum', \substr($program, 4, 2));
        $this->assertIsArray($header);
        $tables = (int) ($header['num'] ?? 0);
        $corrupt = $program;
        for ($idx = 0; $idx < $tables; ++$idx) {
            $record = 12 + ($idx * 16);
            if (\substr($program, $record, 4) === 'glyf') {
                $corrupt = \substr_replace($corrupt, \pack('NN', \strlen($program) - 4, 4), $record + 8, 8);
            }
        }

        $fileHelper = new ObjFile(allowedPaths: FontPaths::buildAllowedPaths());
        $subset = new Subset($corrupt, $fdt, $fileHelper, [65 => true]);

        // the walk terminates instead of reading the tables that follow
        $this->assertNotSame('', $subset->getSubsetFont());
    }

    /**
     * A loca table whose offsets run backwards yields a negative glyph length, which
     * substr() would read as an offset from the end of the string, so the length is
     * clamped to zero and the glyph contributes nothing.
     */
    /** @throws \Com\Tecnick\Pdf\Font\Exception */
    public function testProcessedTablesIgnoreANonMonotonicLocaTable(): void
    {
        $subset = new class() extends Subset {
            public function __construct() {}

            /** @throws \Com\Tecnick\Pdf\Font\Exception */
            public function run(): void
            {
                $this->addProcessedTables();
            }

            public function getGlyfLength(): int
            {
                return \strlen($this->fdt['table']['glyf']['data'] ?? '');
            }
        };

        $fdt = $this->getFontTemplate();

        // two glyphs whose loca offsets decrease: the second length would be -600
        $fdt['tot_num_glyphs'] = 2;
        $fdt['short_offset'] = false;
        $fdt['indexToLoc'] = [0 => 0, 1 => 600, 2 => 0];
        $fdt['table'] = ['glyf' => ['offset' => 0, 'length' => 0, 'checkSum' => 0, 'data' => '']];

        (new \ReflectionProperty($subset, 'font'))->setValue($subset, \str_repeat("\x41", 4096));
        (new \ReflectionProperty($subset, 'fdt'))->setValue($subset, $fdt);
        (new \ReflectionProperty($subset, 'subglyphs'))->setValue($subset, [0 => true, 1 => true]);

        $subset->run();

        // only the first glyph contributes its 600 bytes; the second one is skipped
        $this->assertSame(600, $subset->getGlyfLength());
    }
}
