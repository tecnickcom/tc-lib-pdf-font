<?php

/**
 * PackageFontsTest.php
 *
 * @since     2026-11-15
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
use Com\Tecnick\Pdf\Encrypt\Encrypt;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Font;
use Com\Tecnick\Pdf\Font\FontPaths;
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Output;
use Com\Tecnick\Pdf\Font\Stack;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Lookup and reading of the package font directories with a custom K_PATH_FONTS.
 *
 * Each test runs with K_PATH_FONTS set to an empty directory and with font files placed
 * in the package input directory (getInputPath()).
 *
 * @since     2026-11-15
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class PackageFontsTest extends TestUtil
{
    /** Package input font directory, set once this test owns it. */
    private string $inputDir = '';

    /** K_PATH_FONTS directory. */
    private string $kpath = '';

    /** Directory outside every font directory. */
    private string $outside = '';

    protected function setUp(): void
    {
        $root = \dirname(__DIR__);
        if (\file_exists($root . '/fonts')) {
            $this->markTestSkipped('the package input font directory already exists');
        }

        $base = $root . '/target/tmptest/';
        self::resetDirectory($base);
        $this->kpath = $base . 'kpath/';
        $this->outside = $base . 'outside/';
        self::makeDirectory($this->kpath);
        self::makeDirectory($this->outside);
        self::makeDirectory($root . '/fonts/sub');
        $this->inputDir = $root . '/fonts/';

        \define('K_PATH_FONTS', $this->kpath);
    }

    protected function tearDown(): void
    {
        if ($this->inputDir !== '') {
            self::removeDirectory($this->inputDir);
        }
    }

    #[RunInSeparateProcess]
    public function testPackagePathsAreTheExistingPackageFontDirectories(): void
    {
        $paths = FontPaths::getPackagePaths();

        $this->assertContains(\realpath($this->inputDir), $paths);
        $expected = \array_filter([
            \realpath(FontPaths::getInputPath()),
            \realpath(FontPaths::getOutputPath()),
        ]);
        foreach ($paths as $path) {
            $this->assertContains($path, $expected);
            $this->assertDirectoryExists($path);
        }
    }

    #[RunInSeparateProcess]
    public function testPackagePathsLeaveOutAFilesystemRoot(): void
    {
        $paths = new class extends FontPaths {
            public static function getInputPath(): string
            {
                $dir = __DIR__;
                while (\dirname($dir) !== $dir) {
                    $dir = \dirname($dir);
                }

                return $dir;
            }

            public static function getOutputPath(): string
            {
                return __DIR__ . '/no-such-directory';
            }
        };

        $this->assertSame([], $paths::getPackagePaths());
    }

    /**
     * @throws FontException
     */
    #[RunInSeparateProcess]
    public function testCustomConfigPathFallsBackToThePackageFonts(): void
    {
        $this->writeDefinition($this->inputDir . 'sub/pkgfont.json', 'pkgfont', 400);

        $objnum = 1;
        $metric = (new Stack(1))->insert($objnum, 'pkgfont', '', null, null, null, '', false);

        $this->assertSame('pkgfont', $metric['key']);
    }

    /**
     * @throws FontException
     */
    #[RunInSeparateProcess]
    public function testConfigPathFontTakesPrecedenceOverThePackageFont(): void
    {
        $this->writeDefinition($this->inputDir . 'dupfont.json', 'dupfont', 400);
        $this->writeDefinition($this->kpath . 'dupfont.json', 'dupfont', 700);

        $objnum = 1;
        $stack = new Stack(1);
        $stack->insert($objnum, 'dupfont', '', null, null, null, '', false);
        $font = $stack->getFont('dupfont');

        $this->assertSame(\realpath($this->kpath . 'dupfont.json'), \realpath($font['ifile']));
        $this->assertEquals(700, $font['cw'][65] ?? 0);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws FontException
     */
    #[RunInSeparateProcess]
    public function testPackageFontsDoNotWidenTheCallerFileHelper(): void
    {
        $this->writeDefinition($this->inputDir . 'pkgfont.json', 'pkgfont', 400);
        $caller = new ObjFile(allowedPaths: [$this->outside]);

        $objnum = 1;
        $metric = (new Stack(1, false, true, false, $caller))->insert(
            $objnum,
            'pkgfont',
            '',
            null,
            null,
            null,
            '',
            false,
        );

        $this->assertSame('pkgfont', $metric['key']);
        $this->assertFalse($caller->isAllowedFile($this->inputDir . 'pkgfont.json'));
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws FontException
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     */
    #[RunInSeparateProcess]
    public function testEmbeddedPackageFontIsReadWithoutTheCallerAllowlist(): void
    {
        $source = $this->outside . 'pkgsans.ttf';
        \copy(\dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/freefont/FreeSans.ttf', $source);
        $import = new Import($source, $this->inputDir);
        $this->assertSame('pkgsans', $import->getFontName());
        \unlink($source);

        $caller = new ObjFile(allowedPaths: [$this->outside]);
        $objnum = 1;
        $stack = new Stack(1, true, true, false, $caller);
        $stack->insert($objnum, 'pkgsans', '', 12);
        foreach ([32, 65, 66, 67] as $ord) {
            $stack->addSubsetChar('pkgsans', $ord);
        }

        $reflector = new \ReflectionClass(Encrypt::class);
        $encrypt = $reflector->newInstanceWithoutConstructor();
        $out = (new Output($stack->getFonts(), $objnum, $encrypt, $caller))->getFontsBlock();

        $this->assertStringContainsString('/FontFile2 ', $out);
        $this->assertFalse($caller->isAllowedFile($this->inputDir . 'pkgsans.z'));
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     */
    #[RunInSeparateProcess]
    public function testSymbolicLinkLeavingThePackageDirectoryIsNotFollowed(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('creating a symbolic link needs extra privileges on Windows');
        }

        $target = $this->outside . 'leak.json';
        $this->writeDefinition($target, 'leak', 400);
        $link = $this->inputDir . 'leak.json';
        \symlink($target, $link);

        $this->assertFalse(FontPaths::isPackageFile($link));
        $this->assertFalse(FontPaths::readFontFile(null, $link));
        $this->assertFalse(FontPaths::readFontFile(new ObjFile(allowedPaths: [$this->kpath]), $link));
        $this->assertFalse(FontPaths::readFontFile(new ObjFile(allowedPaths: [$this->outside]), $link));
        // the caller file helper reads the link only when it allows both its path and its target
        $this->assertSame(
            \file_get_contents($target),
            FontPaths::readFontFile(new ObjFile(allowedPaths: [$this->inputDir, $this->outside]), $link),
        );

        $objnum = 1;
        $kpath = $this->kpath;
        $this->assertThrowsMessage(
            FontException::class,
            'unable to read file',
            /** @throws \Throwable */
            static fn() => (new Stack(1, false, true, false, new ObjFile(allowedPaths: [$kpath])))->insert(
                $objnum,
                'leak',
                '',
                null,
                null,
                null,
                '',
                false,
            ),
        );
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     */
    #[RunInSeparateProcess]
    public function testReadFontFileRejectsParentSegmentsAndUrls(): void
    {
        $this->writeDefinition($this->inputDir . 'pkgfont.json', 'pkgfont', 400);

        $this->assertNotFalse(FontPaths::readFontFile(null, $this->inputDir . 'pkgfont.json'));
        $this->assertFalse(FontPaths::readFontFile(null, $this->inputDir . '../composer.json'));
        $this->assertFalse(FontPaths::readFontFile(null, $this->inputDir . 'sub/../pkgfont.json'));
        $this->assertFalse(FontPaths::readFontFile(null, 'https://example.com/pkgfont.json', true));
        $this->assertFalse(FontPaths::isPackageFile(\dirname(__DIR__) . '/composer.json'));
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws FontException
     */
    #[RunInSeparateProcess]
    public function testFontAcceptsAnExplicitPackageDefinitionFile(): void
    {
        $this->writeDefinition($this->inputDir . 'pkgfont.json', 'pkgfont', 400);
        $this->writeDefinition($this->outside . 'other.json', 'other', 400);
        $caller = new ObjFile(allowedPaths: [$this->kpath]);

        $font = new Font('pkgfont', '', $this->inputDir . 'pkgfont.json', false, true, false, true, $caller);
        $font->load();
        $this->assertSame('pkgfont', $font->getFontData()['name']);

        $this->assertThrowsMessage(
            FontException::class,
            'Invalid font ifile',
            /** @throws \Throwable */
            fn() => new Font('other', '', $this->outside . 'other.json', false, true, false, true, $caller),
        );
    }

    /**
     * @throws FontException
     */
    #[RunInSeparateProcess]
    public function testFontFamilyNameCannotCarryAPath(): void
    {
        $this->writeDefinition($this->kpath . 'plainname.json', 'plainname', 400);

        $objnum = 1;
        $metric = (new Stack(1))->insert($objnum, '../../plainname', '', null, null, null, '', false);

        $this->assertSame('plainname', $metric['key']);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws FontException
     * @throws \ReflectionException
     */
    #[RunInSeparateProcess]
    public function testFontDirectoriesAreTheConfigPathThenThePackagePaths(): void
    {
        $font = new Font('pkgfont', '', '', false, true, false, true, new ObjFile());
        $method = new \ReflectionMethod($font, 'findFontDirectories');
        /** @var array<string> $dirs */
        $dirs = $method->invoke($font);

        $roots = [(string) \realpath($this->kpath), ...FontPaths::getPackagePaths()];
        $this->assertSame($roots[0], $dirs[0] ?? '');
        foreach ($dirs as $dir) {
            $inside = \array_filter(
                $roots,
                static fn(string $root): bool => $dir === $root || \str_starts_with($dir, $root . DIRECTORY_SEPARATOR),
            );
            $this->assertNotEmpty($inside, $dir);
        }

        $this->assertContains(\realpath($this->inputDir . 'sub'), $dirs);
    }

    /**
     * Write a minimal Type1 font definition with the width of 'A'.
     */
    private function writeDefinition(string $file, string $name, int $width): void
    {
        \file_put_contents(
            $file,
            '{"type":"Type1","name":"'
            . $name
            . '","dw":600'
            . ',"desc":{"FontBBox":"[0 -200 1000 800]","Ascent":800,"Descent":-200}'
            . ',"cw":{"65":'
            . $width
            . '}}',
        );
    }
}
