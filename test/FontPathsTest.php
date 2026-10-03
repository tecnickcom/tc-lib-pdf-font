<?php

/**
 * FontPathsTest.php
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

use Com\Tecnick\Pdf\Font\FontPaths;
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * Font file lookup rules.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FontPathsTest extends TestUtil
{
    public function testFindFontFileIgnoresAnEmptyFileName(): void
    {
        $this->assertSame('', FontPaths::findFontFile(\dirname(__DIR__), ''));
    }

    /**
     * An empty directory entry is skipped: joined with the file name it would probe the
     * filesystem root.
     */
    public function testFindFontFileNeverProbesTheFilesystemRoot(): void
    {
        $this->assertSame('', FontPaths::findFontFile('', 'etc'));
    }

    /**
     * is_readable() is true for a directory as well, so the lookup also requires a
     * regular file.
     */
    public function testFindFontFileIgnoresADirectoryWithTheRequestedName(): void
    {
        $this->assertSame('', FontPaths::findFontFile(\dirname(__DIR__), 'src'));
    }

    public function testFindFontFileReturnsAnExistingFile(): void
    {
        $found = FontPaths::findFontFile(\dirname(__DIR__), 'composer.json');

        $this->assertStringEndsWith('composer.json', $found);
        $this->assertFileExists($found);
    }

    public function testFindFontFileReturnsAnEmptyStringForAMissingFile(): void
    {
        $this->assertSame('', FontPaths::findFontFile(\dirname(__DIR__), 'no-such-font.json'));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testGetConfigPathIsEmptyWhenUndefined(): void
    {
        $this->assertFalse(\defined('K_PATH_FONTS'), 'this test needs a pristine process');
        $this->assertSame('', FontPaths::getConfigPath());
    }

    /**
     * realpath('') returns the working directory, so an empty value is not resolved.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testGetConfigPathIsEmptyWhenDefinedAsEmpty(): void
    {
        \define('K_PATH_FONTS', '');

        $this->assertSame('', FontPaths::getConfigPath());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testGetConfigPathResolvesParentSegments(): void
    {
        \define('K_PATH_FONTS', \dirname(__DIR__) . '/test/../src/');

        $this->assertSame(\realpath(\dirname(__DIR__) . '/src'), FontPaths::getConfigPath());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testGetConfigPathKeepsAMissingDirectory(): void
    {
        \define('K_PATH_FONTS', '/no/such/../directory/');

        $this->assertSame('/no/such/../directory/', FontPaths::getConfigPath());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testGetConfigPathResolvesADirectoryCreatedAfterTheFirstCall(): void
    {
        $dir = \dirname(__DIR__) . '/target/tmptest/';
        self::removeDirectory($dir);
        \define('K_PATH_FONTS', \dirname(__DIR__) . '/target/../target/tmptest/');

        $this->assertSame((string) \constant('K_PATH_FONTS'), FontPaths::getConfigPath());

        self::makeDirectory($dir);
        $this->assertSame(\realpath($dir), FontPaths::getConfigPath());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testBuildAllowedPathsKeepsTheSymbolicLinkSpelling(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('creating a symbolic link needs extra privileges on Windows');
        }

        $base = \dirname(__DIR__) . '/target/tmptest/';
        self::resetDirectory($base);
        \mkdir($base . 'real');
        \symlink($base . 'real', $base . 'link');
        \define('K_PATH_FONTS', $base . 'link/');

        $allowed = FontPaths::buildAllowedPaths();

        $this->assertContains($base . 'link', $allowed);
        $this->assertContains(\realpath($base . 'real'), $allowed);
    }

    /**
     * A K_PATH_FONTS with '..' segments is resolved before the font files are read.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testFontIsLoadedFromAConfigPathWithParentSegments(): void
    {
        \define('K_PATH_FONTS', \dirname(__DIR__) . '/target/../target/tmptest/');
        self::resetDirectory(\dirname(__DIR__) . '/target/tmptest/');

        $import = new Import(\dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/core/Symbol.afm', '', 'Core');
        $this->assertSame('symbol', $import->getFontName());
        $this->assertFileExists(\dirname(__DIR__) . '/target/tmptest/symbol.json');

        $stack = new Stack(1);
        $objnum = 0;
        $metric = $stack->insert($objnum, 'symbol', '', null, null, null, '', false);
        $this->assertSame('symbol', $metric['key']);
    }
}
