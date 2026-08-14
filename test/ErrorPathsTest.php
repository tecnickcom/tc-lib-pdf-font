<?php

/**
 * ErrorPathsTest.php
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
use Com\Tecnick\Pdf\Font\Import\TrueType;
use Com\Tecnick\Pdf\Font\Import\TypeOne;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * Exercises the failure branches that a malformed font or a bad argument reaches.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class ErrorPathsTest extends TestUtil
{
    private const MIRROR = 'util/vendor/tecnickcom/tc-font-mirror/';

    private function mirror(string $sub): string
    {
        return \dirname(__DIR__) . '/' . self::MIRROR . $sub;
    }

    // -------------------------------------------------------------------------
    // Import argument validation
    // -------------------------------------------------------------------------

    /** @throws \Throwable */
    public function testImportStripsIllegalCharactersFromTheEncodingName(): void
    {
        $this->setupTest();
        // only [A-Za-z0-9_-] survives, so the name can never reach a shell or a path;
        // what is left of it must still be a known encoding
        $import = new Import($this->mirror('core/Helvetica.afm'), $this->getFontPath(), 'Core', 'cp 1252');

        $this->assertSame('cp1252', $import->getFontMetrics()['enc']);
    }

    /** @throws \Throwable */
    public function testImportRejectsAnUnknownEncodingName(): void
    {
        $this->setupTest();
        // an unknown name would reach the definition file unnoticed and produce a font
        // declaring WinAnsi with no /Differences and no width keyed by a glyph name
        $this->assertThrowsMessage(
            FontException::class,
            'Unknown encoding name: cp-1252; rm -rf',
            /** @throws \Throwable */
            fn() => new Import($this->mirror('core/Helvetica.afm'), $this->getFontPath(), 'Core', 'cp-1252; rm -rf'),
        );
    }

    public function testImportRejectsAFontFileNameThatResolvesToNothing(): void
    {
        $this->setupTest();
        // pathinfo() yields an empty filename, so no output name can be derived
        $this->assertThrowsMessage(
            FontException::class,
            'Invalid font file name',
            /** @throws \Throwable */
            fn() => new Import($this->mirror('core/.afm'), $this->getFontPath()),
        );
    }

    public function testImportRejectsAFontFileNameWithoutUsableCharacters(): void
    {
        $this->setupTest();
        $dir = $this->getFontPath();
        // a name made only of characters the sanitizer strips
        \copy($this->mirror('core/Helvetica.afm'), $dir . '+++.afm');

        $this->assertThrowsMessage(
            FontException::class,
            'the font name is empty',
            /** @throws \Throwable */
            static fn() => new Import($dir . '+++.afm', $dir),
        );
    }

    public function testImportRejectsAnUnreadableInputFile(): void
    {
        $this->setupTest();
        $dir = $this->getFontPath();
        $unreadable = $dir . 'noperm.ttf';
        \copy($this->mirror('freefont/FreeSans.ttf'), $unreadable);
        \chmod($unreadable, 0o000);

        if (\is_readable($unreadable)) {
            // running as root: the permission bits do not restrict the read
            \chmod($unreadable, 0o644);
            $this->markTestSkipped('cannot make a file unreadable as this user');
        }

        try {
            $this->assertThrowsMessage(
                FontException::class,
                'unable to read the input font file',
                /** @throws \Throwable */
                static fn() => new Import($unreadable, $dir),
            );
        } finally {
            \chmod($unreadable, 0o644);
        }
    }

    /**
     * With no output path given and K_PATH_FONTS undefined, the importer falls back to the
     * library's own fonts directory (or the temp dir when it cannot be located).
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testFindOutputPathFallsBackWhenNoConfiguredPathIsUsable(): void
    {
        $this->assertFalse(\defined('K_PATH_FONTS'), 'this test needs a pristine process');

        $class = new \ReflectionClass(Import::class);
        $instance = $class->newInstanceWithoutConstructor();
        $ref = new \ReflectionMethod($instance, 'findOutputPath');
        /** @var mixed $result */
        $result = $ref->invoke($instance, '');

        $this->assertIsString($result);
        $this->assertStringEndsWith('/', $result);
        $this->assertDirectoryExists($result);
    }

    public function testBuildAllowedPathsSkipsEmptyRoots(): void
    {
        // dirname() of a bare file name is '.', which is skipped, leaving only the output
        $class = new \ReflectionClass(Import::class);
        $instance = $class->newInstanceWithoutConstructor();
        $ref = new \ReflectionMethod($instance, 'buildAllowedPaths');
        /** @var array<int, string> $allowed */
        $allowed = $ref->invoke(null, 'bare.ttf', '/');

        $this->assertNotContains('', $allowed);
        $this->assertNotContains('.', $allowed);
    }

    // -------------------------------------------------------------------------
    // TrueType structural validation
    // -------------------------------------------------------------------------

    public function testTrueTypeRejectsABadMagicNumber(): void
    {
        $instance = $this->buildTrueTypeStub(\str_repeat("\x00", 32), [
            'table' => ['head' => ['offset' => 0, 'length' => 32, 'checkSum' => 0, 'data' => '']],
        ]);

        $ref = new \ReflectionMethod($instance, 'checkMagickNumber');

        $this->assertThrowsMessage(
            FontException::class,
            'magicNumber',
            /** @throws \Throwable */
            static fn() => $ref->invoke($instance),
        );
    }

    public function testImportLinkedFontFailsWhenTheLinkNameIsTakenByARegularFile(): void
    {
        $this->setupTest();
        $dir = $this->getFontPath();
        // occupy the link name with a regular file so symlink() cannot create it
        \file_put_contents($dir . 'freesans.ttf', 'not a link');

        $this->assertThrowsMessage(
            FontException::class,
            'unable to create the symbolic link',
            /** @throws \Throwable */
            fn() => new Import($this->mirror('freefont/FreeSans.ttf'), $dir, '', '', 32, 3, 1, true),
        );
    }

    /**
     * @param array<string, mixed> $fdt
     */
    private function buildTrueTypeStub(string $font, array $fdt): TrueType
    {
        $class = new \ReflectionClass(TrueType::class);
        $instance = $class->newInstanceWithoutConstructor();
        $byte = new \Com\Tecnick\File\Byte($font);

        foreach (['font' => $font, 'fbyte' => $byte, 'offset' => 0] as $name => $value) {
            $prop = new \ReflectionProperty($instance, $name);
            $prop->setValue($instance, $value);
        }

        $defaults = [
            'Ascent' => 0,
            'Descent' => 0,
            'Flags' => 0,
            'ctgdata' => [],
            'encodingTables' => [],
            'indexToLoc' => [],
            'numGlyphs' => 0,
            'numHMetrics' => 0,
            'subsetchars' => [],
            'table' => [],
            'type' => '',
            'unitsPerEm' => 1000,
            'urk' => 1.0,
        ];
        $fdtProp = new \ReflectionProperty($instance, 'fdt');
        $fdtProp->setValue($instance, \array_replace($defaults, $fdt));

        return $instance;
    }

    // -------------------------------------------------------------------------
    // Type1 charstring decoding
    // -------------------------------------------------------------------------

    private function buildTypeOneStub(): TypeOne
    {
        $class = new \ReflectionClass(TypeOne::class);

        return $class->newInstanceWithoutConstructor();
    }

    /**
     * @return array<string, array{0: array<int, int>}>
     */
    public static function truncatedOperandProvider(): array
    {
        return [
            'the 247..250 form needs one more byte' => [[247]],
            'the 251..254 form needs one more byte' => [[251]],
            'the 255 form needs four more bytes' => [[255, 0, 0]],
        ];
    }

    /**
     * @param array<int, int> $ccom
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('truncatedOperandProvider')]
    public function testDecodeNumberRejectsTruncatedOperands(array $ccom): void
    {
        $instance = $this->buildTypeOneStub();
        /** @var array<int, int> $cdec */
        $cdec = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 0;
        $cid = 0;

        $ref = new \ReflectionMethod($instance, 'decodeNumber');
        $args = [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths];

        $this->assertThrowsMessage(
            FontException::class,
            'Truncated Type1 charstring number operand',
            /** @throws \Throwable */
            static fn() => $ref->invokeArgs($instance, $args),
        );
    }

    /**
     * An operand below 32 that is not the hsbw opcode is stored and skipped.
     */
    public function testDecodeNumberIgnoresNonHsbwLowOpcodes(): void
    {
        $instance = $this->buildTypeOneStub();
        $ccom = [9]; // closepath, not hsbw
        /** @var array<int, int> $cdec */
        $cdec = [0 => 300];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 1;
        $cid = 7;

        $ref = new \ReflectionMethod($instance, 'decodeNumber');
        $args = [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths];
        $this->assertSame(1, $ref->invokeArgs($instance, $args));

        /** @var array<int, int> $cwidths */
        $this->assertSame([], $cwidths, 'only opcode 13 sets a width');
    }

    // -------------------------------------------------------------------------
    // Type1 PFB segment validation
    // -------------------------------------------------------------------------

    /**
     * Build a PFB header: 0x80 0x01, then the little-endian length of segment 1.
     */
    private function pfbHeader(int $len1, string $body = ''): string
    {
        return "\x80\x01" . \pack('V', $len1) . $body;
    }

    public function testTypeOneRejectsASegmentOneLengthBeyondTheFile(): void
    {
        $this->assertTypeOneStoreFails(
            $this->pfbHeader(4096, \str_repeat('A', 16)),
            'segment 1 length exceeds the file size',
        );
    }

    public function testTypeOneRejectsAMissingSecondSegmentMarker(): void
    {
        // segment 1 fits, but the bytes where the second marker belongs are not 0x80 0x02
        $body = \str_repeat('A', 8) . "\x00\x00" . \pack('V', 4);
        $this->assertTypeOneStoreFails($this->pfbHeader(8, $body), 'not a valid binary Type1');
    }

    public function testTypeOneRejectsAClearTextFirstSegment(): void
    {
        // segment 1 must be the ASCII portion (type 1)
        $body = \str_repeat('A', 8) . "\x80\x02" . \pack('V', 4) . 'ABCD';
        $font = "\x80\x02" . \pack('V', 8) . $body;
        $this->assertTypeOneStoreFails($font, 'not a valid binary Type1');
    }

    public function testTypeOneRejectsABinarySecondSegmentDeclaredAsAscii(): void
    {
        // segment 2 is the eexec encrypted portion (type 2): a clear-text segment there
        // would be decrypted into noise and yield a font without charstrings
        $body = \str_repeat('A', 8) . "\x80\x01" . \pack('V', 4) . 'ABCD';
        $this->assertTypeOneStoreFails($this->pfbHeader(8, $body), 'not a valid binary Type1');
    }

    public function testTypeOneRejectsASegmentTwoLengthBeyondTheFile(): void
    {
        $body = \str_repeat('A', 8) . "\x80\x02" . \pack('V', 4096);
        $this->assertTypeOneStoreFails($this->pfbHeader(8, $body), 'segment 2 length exceeds the file size');
    }

    /**
     * A file shorter than the 6-byte segment header is rejected before unpack() is
     * reached, so no warning is emitted.
     */
    public function testTypeOneRejectsAFileShorterThanTheSegmentHeader(): void
    {
        $raised = [];
        \set_error_handler(static function (int $level, string $message) use (&$raised): bool {
            unset($level);
            $raised[] = $message;
            return true;
        });

        try {
            $this->assertTypeOneStoreFails("\x80\x01\x04", 'not a valid binary Type1');
        } finally {
            \restore_error_handler();
        }

        $this->assertSame([], $raised, 'no PHP warning must be raised');
    }

    private function assertTypeOneStoreFails(string $font, string $needle): void
    {
        $instance = $this->buildTypeOneStub();
        $fontProp = new \ReflectionProperty($instance, 'font');
        $fontProp->setValue($instance, $font);
        $fdtProp = new \ReflectionProperty($instance, 'fdt');
        $fdtProp->setValue($instance, [
            'dir' => $this->getFontPath(),
            'file' => 'x.z',
            'file_name' => 'x',
            'linked' => false,
        ]);
        $ref = new \ReflectionMethod($instance, 'storeFontData');

        $this->assertThrowsMessage(
            FontException::class,
            $needle,
            /** @throws \Throwable */
            static fn() => $ref->invoke($instance),
        );
    }

    // -------------------------------------------------------------------------
    // font definition loading
    // -------------------------------------------------------------------------

    /** @throws FontException */
    public function testLoadRejectsAFontDefinitionThatIsNotValidJson(): void
    {
        $this->setupTest();
        \file_put_contents($this->getFontPath() . 'brokenjson.json', '{"type":"Type1",');

        $objnum = 1;
        $stack = new Stack(1);

        $this->assertThrowsMessage(
            FontException::class,
            'JSON decoding error',
            /** @throws \Throwable */
            static fn() => $stack->insert($objnum, 'brokenjson', '', null, null, null, '', null),
        );
    }

    /** @throws FontException */
    public function testFontFamilyNameRejectsAnEmptyName(): void
    {
        $stack = new Stack(1);

        $this->assertThrowsMessage(
            FontException::class,
            'Empty font family name',
            /** @throws \Throwable */
            static fn() => $stack->getFontFamilyName(''),
        );
    }

    // -------------------------------------------------------------------------
    // font file embedding
    // -------------------------------------------------------------------------

    /** @throws \Throwable */
    public function testOutputFailsWhenTheFontProgramCannotBeRead(): void
    {
        $this->setupTest();
        $indir = $this->mirror('freefont/');
        $outdir = $this->getFontPath();

        new Import($indir . 'FreeSans.ttf', $outdir);

        $objnum = 1;
        $stack = new Stack(1);
        $stack->insert($objnum, 'freesans', '', null, null, null, '', false);

        // remove the program the definition points at
        \unlink($outdir . 'freesans.z');

        $encryptClass = new \ReflectionClass(\Com\Tecnick\Pdf\Encrypt\Encrypt::class);
        $encrypt = $encryptClass->newInstanceWithoutConstructor();
        \assert($encrypt instanceof \Com\Tecnick\Pdf\Encrypt\Encrypt, 'the Encrypt stub must be usable');

        $this->assertThrowsMessage(
            FontException::class,
            'Unable to locate the file',
            /** @throws \Throwable */
            static fn() => new \Com\Tecnick\Pdf\Font\Output($stack->getFonts(), $objnum, $encrypt, null),
        );
    }

    /** @throws \Throwable */
    public function testOutputFailsWhenTheFontProgramIsNotValidZlibData(): void
    {
        $this->setupTest();
        $indir = $this->mirror('freefont/');
        $outdir = $this->getFontPath();

        new Import($indir . 'FreeSans.ttf', $outdir);

        $objnum = 1;
        $stack = new Stack(1);
        $stack->insert($objnum, 'freesans', '', null, null, null, '', true);
        $stack->addSubsetChar('freesans', 65);

        // replace the compressed program with garbage
        \file_put_contents($outdir . 'freesans.z', 'not zlib data at all');

        $encryptClass = new \ReflectionClass(\Com\Tecnick\Pdf\Encrypt\Encrypt::class);
        $encrypt = $encryptClass->newInstanceWithoutConstructor();
        \assert($encrypt instanceof \Com\Tecnick\Pdf\Encrypt\Encrypt, 'the Encrypt stub must be usable');

        $this->assertThrowsMessage(
            FontException::class,
            'Unable to uncompress font file',
            /** @throws \Throwable */
            static fn() => new \Com\Tecnick\Pdf\Font\Output($stack->getFonts(), $objnum, $encrypt, null),
        );
    }

    // -------------------------------------------------------------------------
    // Type1 PostScript parsing
    // -------------------------------------------------------------------------

    public function testTypeOneRejectsAFontWithoutAName(): void
    {
        $instance = $this->buildTypeOneStub();
        $fontProp = new \ReflectionProperty($instance, 'font');
        $fontProp->setValue($instance, '%!PS-AdobeFont-1.0: nothing here');
        $fdtProp = new \ReflectionProperty($instance, 'fdt');
        $fdtProp->setValue($instance, ['name' => '']);
        $ref = new \ReflectionMethod($instance, 'extractFontInfo');

        $this->assertThrowsMessage(
            FontException::class,
            'Unable to extract font name',
            /** @throws \Throwable */
            static fn() => $ref->invoke($instance),
        );
    }

    public function testTypeOneReadsTheNameFromFullNameWhenFontNameIsAbsent(): void
    {
        $instance = $this->buildTypeOneStub();
        $fontProp = new \ReflectionProperty($instance, 'font');
        $fontProp->setValue($instance, "/FullName (Test Font 1)\n");
        $fdtProp = new \ReflectionProperty($instance, 'fdt');
        $fdtProp->setValue($instance, ['name' => '']);

        $ref = new \ReflectionMethod($instance, 'extractFontInfo');
        $ref->invoke($instance);

        /** @var mixed $fdt */
        $fdt = $fdtProp->getValue($instance);
        $this->assertIsArray($fdt);
        // the sanitizer strips the spaces
        $this->assertSame('TestFont1', $fdt['name'] ?? null);
    }

    public function testCharstringDataIsEmptyWithoutACharStringsSection(): void
    {
        $instance = $this->buildTypeOneStub();
        $fdtProp = new \ReflectionProperty($instance, 'fdt');
        $fdtProp->setValue($instance, ['enc' => '', 'enc_map' => []]);

        $ref = new \ReflectionMethod($instance, 'getCharstringData');
        /** @var mixed $result */
        $result = $ref->invoke($instance, 'no charstrings section in here');

        $this->assertSame([], $result);
    }

    public function testDecodeNumberStoresALowOpcodeWithAnEmptyOperandStack(): void
    {
        $instance = $this->buildTypeOneStub();
        // cck === 0: there is no preceding operand, so hsbw cannot be applied
        $ccom = [13];
        /** @var array<int, int> $cdec */
        $cdec = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck = 0;
        $cid = 7;

        $ref = new \ReflectionMethod($instance, 'decodeNumber');
        $args = [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths];
        $this->assertSame(1, $ref->invokeArgs($instance, $args));

        /** @var array<int, int> $cwidths */
        $this->assertSame([], $cwidths);
        /** @var array<int, int> $cdec */
        $this->assertSame([0 => 13], $cdec);
    }

    // -------------------------------------------------------------------------
    // unreadable artifacts
    // -------------------------------------------------------------------------

    /**
     * FontPaths::findFontFile() searches the current working directory first, which is not
     * one of the roots the file helper trusts. A file found there is located but refused.
     *
     * @throws \Throwable
     */
    public function testCtgLookupFailsWhenTheArtifactIsOutsideTheTrustedRoots(): void
    {
        $this->setupTest();
        $dir = $this->getFontPath();
        \file_put_contents(
            $dir . 'ctgcwd.json',
            '{"type":"TrueTypeUnicode","name":"ctgcwd","dw":600'
            . ',"desc":{"FontBBox":"[0 -200 1000 800]","Ascent":800,"Descent":-200}'
            . ',"cw":{"65":400},"ctg":"ctgcwd.ctg"}',
        );

        // the current working directory is searched but is not a trusted root
        $planted = (string) \getcwd() . '/ctgcwd.ctg';
        \file_put_contents($planted, \str_repeat("\x00", 131_072));

        try {
            $objnum = 1;
            $stack = new Stack(1);
            $stack->insert($objnum, 'ctgcwd', '', null, null, null, '', false);

            $this->assertThrowsMessage(
                FontException::class,
                'Unable to read font file',
                /** @throws \Throwable */
                static fn() => $stack->getGidForOrd(65),
            );
        } finally {
            \unlink($planted);
        }
    }

    /**
     * The working directory is the last resort of the lookup and is not one of the roots
     * the file helper trusts, so a program that lives only there is located but refused.
     *
     * @throws \Throwable
     */
    public function testFontProgramEmbeddingFailsWhenTheProgramIsOutsideTheTrustedRoots(): void
    {
        $this->setupTest();
        $dir = $this->getFontPath();

        new Import($this->mirror('freefont/FreeSans.ttf'), $dir);

        $objnum = 1;
        $stack = new Stack(1);
        $stack->insert($objnum, 'freesans', '', null, null, null, '', false);

        // leave the working directory as the only place holding the program
        \unlink($dir . 'freesans.z');
        $planted = (string) \getcwd() . '/freesans.z';
        \file_put_contents($planted, 'anything');

        $encryptClass = new \ReflectionClass(\Com\Tecnick\Pdf\Encrypt\Encrypt::class);
        $encrypt = $encryptClass->newInstanceWithoutConstructor();
        \assert($encrypt instanceof \Com\Tecnick\Pdf\Encrypt\Encrypt, 'the Encrypt stub must be usable');

        try {
            $this->assertThrowsMessage(
                FontException::class,
                'Unable to read font file',
                /** @throws \Throwable */
                static fn() => new \Com\Tecnick\Pdf\Font\Output($stack->getFonts(), $objnum, $encrypt, null),
            );
        } finally {
            \unlink($planted);
        }
    }

    /**
     * The directory of the font definition is searched first, ahead of the working
     * directory, so a same-named file left there does not shadow the stored artifact.
     *
     * @throws \Throwable
     */
    public function testAFileInTheWorkingDirectoryDoesNotShadowTheProgramOfTheFont(): void
    {
        $this->setupTest();
        $dir = $this->getFontPath();

        new Import($this->mirror('freefont/FreeSans.ttf'), $dir);

        $objnum = 1;
        $stack = new Stack(1);
        $stack->insert($objnum, 'freesans', '', null, null, null, '', false);

        $planted = (string) \getcwd() . '/freesans.z';
        \file_put_contents($planted, 'anything');

        $encryptClass = new \ReflectionClass(\Com\Tecnick\Pdf\Encrypt\Encrypt::class);
        $encrypt = $encryptClass->newInstanceWithoutConstructor();
        \assert($encrypt instanceof \Com\Tecnick\Pdf\Encrypt\Encrypt, 'the Encrypt stub must be usable');

        try {
            $output = new \Com\Tecnick\Pdf\Font\Output($stack->getFonts(), $objnum, $encrypt, null);
            $this->assertStringContainsString('/FontFile2 ', $output->getFontsBlock());
        } finally {
            \unlink($planted);
        }
    }

    // -------------------------------------------------------------------------
    // font definition directory discovery
    // -------------------------------------------------------------------------

    /**
     * A 'fonts' directory above the library root is searched too, together with each of
     * its immediate subdirectories.
     *
     * @throws \Throwable
     */
    public function testFontDefinitionsAreSearchedInAParentFontsDirectory(): void
    {
        $this->setupTest();
        $parent = \dirname(__DIR__) . '/fonts';
        $sub = $parent . '/vendorpack';
        \system('mkdir -p ' . \escapeshellarg($sub));

        try {
            \file_put_contents(
                $sub . '/parentfont.json',
                '{"type":"Type1","name":"parentfont","dw":600'
                . ',"desc":{"FontBBox":"[0 -200 1000 800]","Ascent":800,"Descent":-200}'
                . ',"cw":{"65":400}}',
            );

            $objnum = 1;
            $stack = new Stack(1);
            $metric = $stack->insert($objnum, 'parentfont', '', null, null, null, '', false);

            $this->assertSame('parentfont', $metric['key']);
        } finally {
            \system('rm -rf ' . \escapeshellarg($parent));
        }
    }

    /**
     * A K_PATH_FONTS of '/' normalizes to the empty string and is dropped rather than
     * trusted as a root that would match every path.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testBuildAllowedPathsDropsARootThatNormalizesToNothing(): void
    {
        \define('K_PATH_FONTS', '/');

        $allowed = \Com\Tecnick\Pdf\Font\FontPaths::buildAllowedPaths();

        $this->assertNotContains('', $allowed);
        $this->assertNotContains('/', $allowed);
        $this->assertContains(\rtrim(\Com\Tecnick\Pdf\Font\FontPaths::getInputPath(), '/'), $allowed);
    }

    /**
     * A font whose definition was supplied without a directory cannot have a sibling
     * style file, so the style lookup falls straight through to autodetection.
     */
    /** @throws FontException|\ReflectionException */
    public function testStyleFontFileIsSkippedForAFontWithoutADirectory(): void
    {
        $stack = new Stack(1);
        $ref = new \ReflectionMethod($stack, 'getStyleFontFile');

        $this->assertSame('', $ref->invoke($stack, ['dir' => '', 'family' => 'x'], 'B'));
    }

    /**
     * A definition whose 'type' is not a string is rejected before checkType() reads it.
     */
    /** @throws FontException */
    public function testLoadRejectsADefinitionWhoseTypeIsNotAString(): void
    {
        $this->setupTest();
        \file_put_contents($this->getFontPath() . 'badtype.json', '{"type":{"nested":"value"}}');

        $objnum = 1;
        $stack = new Stack(1);

        $this->assertThrowsMessage(
            FontException::class,
            'The font definition file has a bad format',
            /** @throws \Throwable */
            static fn() => $stack->insert($objnum, 'badtype', '', null, null, null, '', null),
        );
    }
}
