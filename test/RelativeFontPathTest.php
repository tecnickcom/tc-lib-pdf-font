<?php

/**
 * RelativeFontPathTest.php
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
use Com\Tecnick\Pdf\Font\Import;

/**
 * A font path relative to the working directory is imported like an absolute one.
 *
 * The trusted root of an import is the directory of the font, and the file helper compares
 * path strings to decide whether a path is inside a root, so a relative path is anchored to
 * the working directory before the allowlist is built.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class RelativeFontPathTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/core/Symbol.afm';

    /**
     * Run the callback with the working directory set to the font directory.
     *
     * @throws \Throwable
     */
    private function inFontDirectory(callable $callback): mixed
    {
        $previous = \getcwd();
        $this->assertIsString($previous);
        \copy(\dirname(__DIR__) . self::MIRROR, $this->getFontPath() . 'Symbol.afm');
        $this->assertTrue(\chdir($this->getFontPath()));

        try {
            return $callback();
        } finally {
            \chdir($previous);
        }
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \Throwable
     */
    public function testABareFileNameIsResolvedAgainstTheWorkingDirectory(): void
    {
        $this->setupTest();
        $output = $this->getFontPath();

        /** @var mixed $name */
        $name = $this->inFontDirectory(
            /** @throws \Throwable */
            static fn(): string => (new Import('Symbol.afm', $output, 'Core'))->getFontName(),
        );

        $this->assertSame('symbol', $name);
        $this->assertFileExists($output . 'symbol.json');
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \Throwable
     */
    public function testADotRelativePathIsResolvedTheSameWay(): void
    {
        $this->setupTest();
        $output = $this->getFontPath();

        /** @var mixed $name */
        $name = $this->inFontDirectory(
            /** @throws \Throwable */
            static fn(): string => (new Import('./Symbol.afm', $output, 'Core'))->getFontName(),
        );

        $this->assertSame('symbol', $name);
    }

    /**
     * An absolute path is left exactly as it was given.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAnAbsolutePathIsUnaffected(): void
    {
        $this->setupTest();
        $import = new Import(\dirname(__DIR__) . self::MIRROR, $this->getFontPath(), 'Core');

        $this->assertSame('symbol', $import->getFontName());
    }

    /**
     * A stream URL is not a filesystem path: it is left unanchored and refused by the
     * file helper.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testAStreamUrlIsStillRefused(): void
    {
        $this->setupTest();
        $output = $this->getFontPath();

        $this->assertThrowsMessage(
            FontException::class,
            'Invalid font file name: http://example.com/Symbol.afm',
            /** @throws \Throwable */
            static fn() => new Import('http://example.com/Symbol.afm', $output, 'Core'),
        );
    }
}
