<?php

/**
 * SubsetTableOrderTest.php
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
use Com\Tecnick\File\File as ObjFile;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\FontPaths;
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Load;
use Com\Tecnick\Pdf\Font\Subset;
use Com\Tecnick\Pdf\Font\Zlib;

/**
 * The table directory of a subset is sorted by tag.
 *
 * The sfnt specification requires the entries of a table directory to be in ascending tag
 * order, so the subset sorts them rather than inheriting the order of the input program.
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
class SubsetTableOrderTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/';

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTheDirectoryIsSortedWhateverTheOrderOfTheInputOne(): void
    {
        $program = $this->importFreeSerif();

        $sorted = (new SfntReader($this->subset($program)))->getTags();
        $this->assertSame($this->getSortedCopy($sorted), $sorted, 'a well formed input');

        // The records are reversed in place. The offsets a directory carries are absolute,
        // so the program stays loadable and only the order of the entries changes.
        $shuffled = (new SfntReader($this->subset($this->reverseTableDirectory($program))))->getTags();
        $this->assertSame($this->getSortedCopy($shuffled), $shuffled, 'an unsorted input');

        // the same tables survive either way
        $this->assertSame($sorted, $shuffled);
    }

    /**
     * A sorted directory is only useful if it still describes where the tables are.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testEveryTableLiesAtTheAdvertisedOffset(): void
    {
        $subset = $this->subset($this->reverseTableDirectory($this->importFreeSerif()));
        $reader = new SfntReader($subset);

        $end = $reader->getDirectoryEnd();
        foreach ($reader->getRecords() as $tag => $record) {
            $this->assertGreaterThanOrEqual($end, $record['offset'], $tag . ' starts after the directory');
            $this->assertSame(0, $record['offset'] % 4, $tag . ' starts on a four byte boundary');
            $this->assertLessThanOrEqual(
                \strlen($subset),
                $record['offset'] + $record['length'],
                $tag . ' lies inside the program',
            );
        }

        // the head table is the one buildSubsetFont() writes the checksum adjustment into,
        // so its record has to point at the real bytes for the program to be valid
        $head = $reader->getTable('head');
        $this->assertSame("\x5F\x0F\x3C\xF5", \substr($head, 12, 4), 'the head magic number');
    }

    /**
     * Imports FreeSerif and returns the uncompressed program it stored.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    private function importFreeSerif(): string
    {
        $this->setupTest();
        $import = new Import(\dirname(__DIR__) . self::MIRROR . 'freefont/FreeSerif.ttf');

        $stored = (string) \file_get_contents($this->getFontPath() . $import->getFontName() . '.z');
        $program = Zlib::uncompress($stored);
        $this->assertNotFalse($program);

        return $program;
    }

    /**
     * Returns the metrics FreeSerif was imported with.
     *
     * @return TFontData
     */
    private function getFontMetrics(): array
    {
        $files = \glob($this->getFontPath() . '*.json');
        $this->assertNotFalse($files);
        $this->assertNotSame([], $files);

        /** @var array<array-key, mixed> $decoded */
        $decoded = \json_decode((string) \file_get_contents((string) \reset($files)), true);

        /** @var TFontData $merged */
        $merged = \array_replace_recursive(Load::DEFAULT_DATA, $decoded);

        return $merged;
    }

    /**
     * Returns the subset of a font program.
     *
     * @throws FileException
     * @throws FontException
     */
    private function subset(string $program): string
    {
        return (new Subset(
            $program,
            $this->getFontMetrics(),
            new ObjFile(allowedPaths: FontPaths::buildAllowedPaths()),
            [0x41 => true, 0x42 => true],
        ))->getSubsetFont();
    }

    /**
     * Returns a copy of a font program with the records of its table directory reversed.
     */
    private function reverseTableDirectory(string $program): string
    {
        $count = \count((new SfntReader($program))->getTags());
        $records = [];
        for ($idx = 0; $idx < $count; ++$idx) {
            $records[] = \substr($program, 12 + ($idx * 16), 16);
        }

        return (
            \substr($program, 0, 12) . \implode('', \array_reverse($records)) . \substr($program, 12 + ($count * 16))
        );
    }

    /**
     * @param array<int, string> $tags Table tags.
     *
     * @return array<int, string>
     */
    private function getSortedCopy(array $tags): array
    {
        \sort($tags, SORT_STRING);
        return $tags;
    }
}
