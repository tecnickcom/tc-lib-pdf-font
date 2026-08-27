<?php

/**
 * ImportArtifactsTest.php
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
use Com\Tecnick\Pdf\Font\Import;

/**
 * The files an import leaves in the output directory.
 *
 * A failed import removes the artifacts it created.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class ImportArtifactsTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/';

    /**
     * A TrueType font whose header and table directory are valid but whose tables are not,
     * so the program is stored before the parsing fails.
     */
    private function buildUnparsableTrueType(): string
    {
        return (
            "\x00\x01\x00\x00" // sfnt version 1.0
            . "\x00\x01" // numTables = 1
            . "\x00\x10\x00\x04\x00\x00" // searchRange, entrySelector, rangeShift
            . 'cmap' // a table directory without the required 'head'
            . "\x00\x00\x00\x00" // checksum
            . "\x00\x00\x00\x1C" // offset
            . "\x00\x00\x00\x04" // length
            . "\x00\x00\x00\x00"
        );
    }

    /** @throws \Throwable */
    public function testAFailedImportLeavesNoArtifactBehind(): void
    {
        $this->setupTest();
        $dir = $this->getFontPath();
        \file_put_contents($dir . 'broken.ttf', $this->buildUnparsableTrueType());

        $this->assertThrowsMessage(
            FontException::class,
            'table',
            /** @throws \Throwable */
            static fn() => new Import($dir . 'broken.ttf', $dir),
        );

        $this->assertFileDoesNotExist($dir . 'broken.z', 'the stored font program');
        $this->assertFileDoesNotExist($dir . 'broken.ctg.z', 'the CIDToGIDMap');
        $this->assertFileDoesNotExist($dir . 'broken.json');
    }

    /**
     * Only the files the failed import created are removed: a linked font reuses an
     * existing symbolic link, which is left in place.
     *
     * @throws \Throwable
     */
    public function testAFailedImportKeepsTheSymbolicLinkItDidNotCreate(): void
    {
        $this->setupTest();
        $dir = $this->getFontPath();
        \mkdir($dir . 'src');
        $source = $dir . 'src/broken.ttf';
        \file_put_contents($source, $this->buildUnparsableTrueType());

        $link = $dir . 'broken.ttf';
        \symlink($source, $link);

        $this->assertThrowsMessage(
            FontException::class,
            'table',
            /** @throws \Throwable */
            static fn() => new Import($source, $dir, '', '', 32, 3, 1, true),
        );

        $this->assertTrue(\is_link($link), 'the link of the already imported font');
        $this->assertSame($source, \readlink($link));
    }

    /**
     * A successful import keeps its artifacts, and a font whose character codes are glyph
     * indices is the one that needs the CIDToGIDMap.
     *
     * @throws \Throwable
     */
    public function testUnicodeImportStoresTheCIDToGIDMap(): void
    {
        $this->setupTest();
        $dir = $this->getFontPath();
        $import = new Import(\dirname(__DIR__) . self::MIRROR . 'freefont/FreeSans.ttf', $dir);

        $this->assertSame('TrueTypeUnicode', $import->getFontMetrics()['type']);
        $this->assertFileExists($dir . 'freesans.ctg.z');
        $this->assertStringContainsString(
            '"ctg":"freesans.ctg.z"',
            (string) \file_get_contents($dir . 'freesans.json'),
        );
    }

    /**
     * A byte encoded font addresses its glyphs by character code, so no CIDToGIDMap
     * artifact is written for it.
     *
     * @throws \Throwable
     */
    public function testByteEncodedImportStoresNoCIDToGIDMap(): void
    {
        $this->setupTest();
        $dir = $this->getFontPath();
        $import = new Import(\dirname(__DIR__) . self::MIRROR . 'freefont/FreeSans.ttf', $dir, 'TrueType');

        $metrics = $import->getFontMetrics();
        $this->assertSame('TrueType', $metrics['type']);
        $this->assertSame('', $metrics['ctg'], 'the metrics must not name an artifact that is not there');
        $this->assertFileDoesNotExist($dir . 'freesans.ctg.z');

        $json = (string) \file_get_contents($dir . 'freesans.json');
        $this->assertStringNotContainsString('"ctg"', $json);
        // the font program itself is still stored and named
        $this->assertStringContainsString('"file":"freesans.z"', $json);
        $this->assertFileExists($dir . 'freesans.z');
    }

    /**
     * isValidFile() trims the name it validates, so the path is trimmed before it is
     * recorded and opened.
     *
     * @throws \Throwable
     */
    public function testInputPathSurroundedByWhitespaceIsAccepted(): void
    {
        $this->setupTest();
        $dir = $this->getFontPath();
        $import = new Import(' ' . \dirname(__DIR__) . self::MIRROR . 'core/Helvetica.afm' . "\n", $dir);

        $this->assertSame('helvetica', $import->getFontName());
        $this->assertFileExists($dir . 'helvetica.json');
    }
}
