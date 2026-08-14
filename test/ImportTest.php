<?php

/**
 * ImportTest.php
 *
 * @since     2011-05-23
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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Import Test
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @SuppressWarnings("PHPMD.LongVariable")
 *
 * @phpstan-import-type TFontData from \Com\Tecnick\Pdf\Font\Load
 */
class ImportTest extends TestUtil
{
    private function expectFontException(): void
    {
        $this->bcExpectException(\Com\Tecnick\Pdf\Font\Exception::class);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportForbiddenProtocol(): void
    {
        $this->expectFontException();
        new \Com\Tecnick\Pdf\Font\Import('phar://test.txt');
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportParentDir(): void
    {
        $this->expectFontException();
        new \Com\Tecnick\Pdf\Font\Import('/tmp/something/../test.txt');
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportEmptyName(): void
    {
        $this->expectFontException();
        new \Com\Tecnick\Pdf\Font\Import('');
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportExist(): void
    {
        $this->expectFontException();
        $fin = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/core/Helvetica.afm';
        $outdir = \dirname(__DIR__) . '/target/tmptest/';
        \system('rm -rf ' . $outdir . ' && mkdir -p ' . $outdir);
        new \Com\Tecnick\Pdf\Font\Import($fin, $outdir);
        new \Com\Tecnick\Pdf\Font\Import($fin, $outdir);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportWrongFile(): void
    {
        $this->expectFontException();
        new \Com\Tecnick\Pdf\Font\Import(\dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/core/Missing.afm');
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportDefaultOutput(): void
    {
        $this->expectFontException();
        new \Com\Tecnick\Pdf\Font\Import(\dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/core/Missing.afm');
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportUnsupportedType(): void
    {
        $this->expectFontException();
        $fin = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/core/Helvetica.afm';
        $outdir = \dirname(__DIR__) . '/target/tmptest/core/';
        \system('rm -rf ' . $outdir . ' && mkdir -p ' . $outdir);
        new \Com\Tecnick\Pdf\Font\Import($fin, $outdir, 'ERROR');
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportUnsupportedOpenType(): void
    {
        $this->expectFontException();
        $outdir = \dirname(__DIR__) . '/target/tmptest/core/';
        \system('rm -rf ' . $outdir . ' && mkdir -p ' . $outdir);
        \file_put_contents($outdir . 'test.ttf', 'OTTO 1234');
        new \Com\Tecnick\Pdf\Font\Import($outdir . 'test.ttf', $outdir);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \JsonException
     * @throws \RangeException
     */
    #[DataProvider('importDataProvider')]
    public function testImport(
        string $fontdir,
        string $font,
        string $outname,
        string $type = '',
        string $encoding = '',
    ): void {
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/' . $fontdir . '/';
        $outdir = \dirname(__DIR__) . '/target/tmptest/' . $fontdir . '/';
        \system('rm -rf ' . \dirname(__DIR__) . '/target/tmptest/ && mkdir -p ' . $outdir);

        $import = new \Com\Tecnick\Pdf\Font\Import($indir . $font, $outdir, $type, $encoding);
        $this->assertEquals($outname, $import->getFontName());

        $file = \file_get_contents($outdir . $outname . '.json');
        $this->assertNotFalse($file);

        /**
         * @var array{
         *     type: mixed,
         *     name: mixed,
         *     up: mixed,
         *     ut: mixed,
         *     dw: mixed,
         *     diff: mixed,
         *     desc: array{
         *         Flags: mixed,
         *         FontBBox: mixed,
         *         ItalicAngle: mixed,
         *         Ascent: mixed,
         *         Descent: mixed,
         *         Leading: mixed,
         *         CapHeight: mixed,
         *         XHeight: mixed,
         *         StemV: mixed,
         *         StemH: mixed,
         *         AvgWidth: mixed,
         *         MaxWidth: mixed,
         *         MissingWidth: mixed
         *     }
         * } $json
         */
        $json = \json_decode($file, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('type', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('up', $json);
        $this->assertArrayHasKey('ut', $json);
        $this->assertArrayHasKey('dw', $json);
        $this->assertArrayHasKey('diff', $json);
        $this->assertArrayHasKey('desc', $json);
        $this->assertArrayHasKey('Flags', $json['desc']);

        $metric = $import->getFontMetrics();

        $this->assertEquals('[' . $metric['bbox'] . ']', $json['desc']['FontBBox']);
        $this->assertEqualsWithDelta($metric['italicAngle'], $json['desc']['ItalicAngle'], 0.001);
        $this->assertEquals($metric['Ascent'], $json['desc']['Ascent']);
        $this->assertEquals($metric['Descent'], $json['desc']['Descent']);
        $this->assertEquals($metric['Leading'], $json['desc']['Leading']);
        $this->assertEquals($metric['CapHeight'], $json['desc']['CapHeight']);
        $this->assertEquals($metric['XHeight'], $json['desc']['XHeight']);
        $this->assertEquals($metric['StemV'], $json['desc']['StemV']);
        $this->assertEquals($metric['StemH'], $json['desc']['StemH']);
        $this->assertEquals($metric['AvgWidth'], $json['desc']['AvgWidth']);
        $this->assertEquals($metric['MaxWidth'], $json['desc']['MaxWidth']);
        $this->assertEquals($metric['MissingWidth'], $json['desc']['MissingWidth']);
    }

    /**
     * @return array<array<string>>
     */
    public static function importDataProvider(): array
    {
        return [
            ['core', 'Courier.afm', 'courier'],
            ['core', 'Courier-Bold.afm', 'courierb'],
            ['core', 'Courier-BoldOblique.afm', 'courierbi'],
            ['core', 'Courier-Oblique.afm', 'courieri'],
            ['core', 'Helvetica.afm', 'helvetica'],
            ['core', 'Helvetica-Bold.afm', 'helveticab'],
            ['core', 'Helvetica-BoldOblique.afm', 'helveticabi'],
            ['core', 'Helvetica-Oblique.afm', 'helveticai'],
            ['core', 'Symbol.afm', 'symbol'],
            ['core', 'Times.afm', 'times'],
            ['core', 'Times-Bold.afm', 'timesb'],
            ['core', 'Times-BoldItalic.afm', 'timesbi'],
            ['core', 'Times-Italic.afm', 'timesi'],
            ['core', 'ZapfDingbats.afm', 'zapfdingbats'],
            ['pdfa/pfb', 'PDFACourierBoldOblique.pfb', 'pdfacourierbi', '', ''],
            ['pdfa/pfb', 'PDFACourierBold.pfb', 'pdfacourierb', 'Type1', 'cp1252'],
            ['pdfa/pfb', 'PDFACourierOblique.pfb', 'pdfacourieri', 'Type1', 'cp1252'],
            ['pdfa/pfb', 'PDFACourier.pfb', 'pdfacourier', 'Type1', 'cp1252'],
            ['pdfa/pfb', 'PDFAHelveticaBoldOblique.pfb', 'pdfahelveticabi', 'Type1', 'cp1252'],
            ['pdfa/pfb', 'PDFAHelveticaBold.pfb', 'pdfahelveticab', 'Type1', 'cp1252'],
            ['pdfa/pfb', 'PDFAHelveticaOblique.pfb', 'pdfahelveticai', 'Type1', 'cp1252'],
            ['pdfa/pfb', 'PDFAHelvetica.pfb', 'pdfahelvetica', 'Type1', 'cp1252'],
            ['pdfa/pfb', 'PDFASymbol.pfb', 'pdfasymbol', '', 'symbol'],
            ['pdfa/pfb', 'PDFATimesBoldItalic.pfb', 'pdfatimesbi', 'Type1', 'cp1252'],
            ['pdfa/pfb', 'PDFATimesBold.pfb', 'pdfatimesb', 'Type1', 'cp1252'],
            ['pdfa/pfb', 'PDFATimesItalic.pfb', 'pdfatimesi', 'Type1', 'cp1252'],
            ['pdfa/pfb', 'PDFATimes.pfb', 'pdfatimes', 'Type1', 'cp1252'],
            ['pdfa/pfb', 'PDFAZapfDingbats.pfb', 'pdfazapfdingbats'],
            ['freefont', 'FreeMonoBoldOblique.ttf', 'freemonobi'],
            ['freefont', 'FreeMonoBold.ttf', 'freemonob'],
            ['freefont', 'FreeMonoOblique.ttf', 'freemonoi'],
            ['freefont', 'FreeMono.ttf', 'freemono'],
            ['freefont', 'FreeSansBoldOblique.ttf', 'freesansbi'],
            ['freefont', 'FreeSansBold.ttf', 'freesansb'],
            ['freefont', 'FreeSansOblique.ttf', 'freesansi'],
            ['freefont', 'FreeSans.ttf', 'freesans'],
            ['freefont', 'FreeSerifBoldItalic.ttf', 'freeserifbi'],
            ['freefont', 'FreeSerifBold.ttf', 'freeserifb'],
            ['freefont', 'FreeSerifItalic.ttf', 'freeserifi'],
            ['freefont', 'FreeSerif.ttf', 'freeserif'],
            ['unifont', 'unifont.ttf', 'unifont'],
            ['cid0', 'cid0cs.ttf', 'cid0cs', 'CID0CS'],
            ['cid0', 'cid0ct.ttf', 'cid0ct', 'CID0CT'],
            ['cid0', 'cid0jp.ttf', 'cid0jp', 'CID0JP'],
            ['cid0', 'cid0kr.ttf', 'cid0kr', 'CID0KR'],
            ['dejavu/ttf', 'DejaVuSans.ttf', 'dejavusans'],
            ['dejavu/ttf', 'DejaVuSans-BoldOblique.ttf', 'dejavusansbi'],
            ['dejavu/ttf', 'DejaVuSans-Bold.ttf', 'dejavusansb'],
            ['dejavu/ttf', 'DejaVuSans-Oblique.ttf', 'dejavusansi'],
            ['dejavu/ttf', 'DejaVuSansCondensed.ttf', 'dejavusanscondensed'],
            ['dejavu/ttf', 'DejaVuSansCondensed-BoldOblique.ttf', 'dejavusanscondensedbi'],
            ['dejavu/ttf', 'DejaVuSansCondensed-Bold.ttf', 'dejavusanscondensedb'],
            ['dejavu/ttf', 'DejaVuSansCondensed-Oblique.ttf', 'dejavusanscondensedi'],
            ['dejavu/ttf', 'DejaVuSansMono.ttf', 'dejavusansmono'],
            ['dejavu/ttf', 'DejaVuSansMono-BoldOblique.ttf', 'dejavusansmonobi'],
            ['dejavu/ttf', 'DejaVuSansMono-Bold.ttf', 'dejavusansmonob'],
            ['dejavu/ttf', 'DejaVuSansMono-Oblique.ttf', 'dejavusansmonoi'],
            ['dejavu/ttf', 'DejaVuSans-ExtraLight.ttf', 'dejavusansextralight'],
            ['dejavu/ttf', 'DejaVuSerif.ttf', 'dejavuserif'],
            ['dejavu/ttf', 'DejaVuSerif-BoldItalic.ttf', 'dejavuserifbi'],
            ['dejavu/ttf', 'DejaVuSerif-Bold.ttf', 'dejavuserifb'],
            ['dejavu/ttf', 'DejaVuSerif-Italic.ttf', 'dejavuserifi'],
            ['dejavu/ttf', 'DejaVuSerifCondensed.ttf', 'dejavuserifcondensed'],
            ['dejavu/ttf', 'DejaVuSerifCondensed-BoldItalic.ttf', 'dejavuserifcondensedbi'],
            ['dejavu/ttf', 'DejaVuSerifCondensed-Bold.ttf', 'dejavuserifcondensedb'],
            ['dejavu/ttf', 'DejaVuSerifCondensed-Italic.ttf', 'dejavuserifcondensedi'],
        ];
    }

    // -------------------------------------------------------------------------
    // linked fonts
    // -------------------------------------------------------------------------

    /**
     * A linked font records a symlink to the original program instead of copying it. The
     * link keeps the extension of the input file and is recorded in 'file'.
     *
     * @throws FileException
     * @throws FontException
     * @throws \JsonException
     * @throws \RangeException
     */
    public function testImportLinkedFontRecordsAndCreatesTheLink(): void
    {
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/freefont/';
        $outdir = \dirname(__DIR__) . '/target/tmptest/linked/';
        \system('rm -rf ' . \escapeshellarg($outdir) . ' && mkdir -p ' . \escapeshellarg($outdir));

        $import = new \Com\Tecnick\Pdf\Font\Import($indir . 'FreeSans.ttf', $outdir, '', '', 32, 3, 1, true);

        $this->assertSame('freesans', $import->getFontName());

        $metrics = $import->getFontMetrics();
        $this->assertSame('freesans.ttf', $metrics['file']);

        $link = $outdir . 'freesans.ttf';
        $this->assertTrue(\is_link($link), 'the symlink must exist');
        $this->assertSame(\realpath($indir . 'FreeSans.ttf'), \realpath($link));

        // no compressed copy of the program is written in linked mode
        $this->assertFileDoesNotExist($outdir . 'freesans.z');

        $json = \file_get_contents($outdir . 'freesans.json');
        $this->assertNotFalse($json);
        /** @var array<string, mixed> $decoded */
        $decoded = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertSame('freesans.ttf', $decoded['file'] ?? null);
    }

    /**
     * The link name carries no '.z' suffix, which is how Output tells a raw program from
     * a compressed one, and the emitted stream is valid FlateDecode data.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     * @throws \ReflectionException
     */
    public function testLinkedFontIsEmbeddedAsAValidFlateStream(): void
    {
        // the definition must live in a trusted root for the font loader to accept it
        $this->setupTest();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/freefont/';
        $outdir = $this->getFontPath();

        new \Com\Tecnick\Pdf\Font\Import($indir . 'FreeSans.ttf', $outdir, '', '', 32, 3, 1, true);

        $objnum = 1;
        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $stack->insert($objnum, 'freesans', '', null, null, null, '', false);

        $encryptClass = new \ReflectionClass(\Com\Tecnick\Pdf\Encrypt\Encrypt::class);
        $encrypt = $encryptClass->newInstanceWithoutConstructor();
        \assert($encrypt instanceof \Com\Tecnick\Pdf\Encrypt\Encrypt, 'the Encrypt stub must be usable');

        // the symlink resolves into the original font directory, which the default
        // render-time allowlist does not trust: linked mode needs an explicit helper
        $helper = new \Com\Tecnick\File\File(allowedPaths: [\rtrim($outdir, '/'), \rtrim($indir, '/')]);

        $output = new \Com\Tecnick\Pdf\Font\Output($stack->getFonts(), $objnum, $encrypt, $helper);
        $block = $output->getFontsBlock();

        // the font program was embedded rather than skipped
        $this->assertStringContainsString('/Filter /FlateDecode', $block);
        $this->assertStringContainsString('/Length1 ', $block);

        $found = [];
        $this->assertSame(1, \preg_match('#/Length1 (\d+)#', $block, $found));
        // Length1 is the uncompressed program size: the whole FreeSans file
        $this->assertSame(\filesize($indir . 'FreeSans.ttf'), (int) ($found[1] ?? 0));
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportLinkedFontToleratesAnExistingLink(): void
    {
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/freefont/';
        $outdir = \dirname(__DIR__) . '/target/tmptest/linkedtwice/';
        \system('rm -rf ' . \escapeshellarg($outdir) . ' && mkdir -p ' . \escapeshellarg($outdir));

        // pre-create the link the import is about to make
        \symlink($indir . 'FreeSans.ttf', $outdir . 'freesans.ttf');

        $import = new \Com\Tecnick\Pdf\Font\Import($indir . 'FreeSans.ttf', $outdir, '', '', 32, 3, 1, true);

        $this->assertSame('freesans.ttf', $import->getFontMetrics()['file']);
    }

    /**
     * getFontType() maps any 'CID0*' name to the cidfont0 type, but only the four known
     * collections have a CIDSystemInfo block to emit, so an unknown one is refused.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportRejectsAnUnknownCid0Collection(): void
    {
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/cid0/';
        $outdir = \dirname(__DIR__) . '/target/tmptest/cid0bad/';
        \system('rm -rf ' . \escapeshellarg($outdir) . ' && mkdir -p ' . \escapeshellarg($outdir));

        $this->assertThrowsMessage(
            \Com\Tecnick\Pdf\Font\Exception::class,
            'unknown or unsupported CID-0 font type',
            /** @throws \Throwable */
            static fn() => new \Com\Tecnick\Pdf\Font\Import($indir . 'cid0jp.ttf', $outdir, 'CID0XX'),
        );
    }

    /**
     * Every CID-0 collection must produce a definition file that decodes.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportedCid0DefinitionsAreValidJson(): void
    {
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/cid0/';
        $outdir = \dirname(__DIR__) . '/target/tmptest/cid0json/';
        \system('rm -rf ' . \escapeshellarg($outdir) . ' && mkdir -p ' . \escapeshellarg($outdir));

        foreach (['jp' => 'CID0JP', 'kr' => 'CID0KR', 'cs' => 'CID0CS', 'ct' => 'CID0CT'] as $suffix => $type) {
            $import = new \Com\Tecnick\Pdf\Font\Import($indir . 'cid0' . $suffix . '.ttf', $outdir, $type);
            $written = \file_get_contents($outdir . $import->getFontName() . '.json');
            $this->assertIsString($written);
            $this->assertIsArray(\json_decode($written, true), $type . ' must produce valid JSON');
        }
    }

    /**
     * A TrueType Collection is not supported and must be reported as such instead of
     * falling through to the Type 1 branch.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportRejectsATrueTypeCollection(): void
    {
        $outdir = \dirname(__DIR__) . '/target/tmptest/ttc/';
        \system('rm -rf ' . \escapeshellarg($outdir) . ' && mkdir -p ' . \escapeshellarg($outdir));
        \file_put_contents($outdir . 'collection.ttc', 'ttcf' . \str_repeat("\x00", 32));

        $this->assertThrowsMessage(
            \Com\Tecnick\Pdf\Font\Exception::class,
            'TrueType Collection',
            /** @throws \Throwable */
            static fn() => new \Com\Tecnick\Pdf\Font\Import($outdir . 'collection.ttc', $outdir),
        );
    }

    /**
     * Every format carrying an sfnt-like signature this library cannot read is named in
     * the error.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testImportNamesEveryUnsupportedFontFormat(): void
    {
        $outdir = \dirname(__DIR__) . '/target/tmptest/unsupported/';
        \system('rm -rf ' . \escapeshellarg($outdir) . ' && mkdir -p ' . \escapeshellarg($outdir));

        $cases = [
            'wOFF' => 'WOFF',
            'wOF2' => 'WOFF2',
            'true' => 'legacy Macintosh sfnt',
            'typ1' => 'sfnt-housed Type1',
            '%!PS-AdobeFont-1.0' => 'PFA',
        ];

        $idx = 0;
        foreach ($cases as $signature => $needle) {
            $file = $outdir . 'sample' . $idx++ . '.ttf';
            \file_put_contents($file, $signature . \str_repeat("\x00", 32));

            $this->assertThrowsMessage(
                \Com\Tecnick\Pdf\Font\Exception::class,
                $needle,
                /** @throws \Throwable */
                static fn() => new \Com\Tecnick\Pdf\Font\Import($file, $outdir),
            );
        }
    }

    /**
     * A corrupt font program makes the byte reader run past the end of the string, and the
     * RangeException it raises is reported as a FontException.
     *
     * @throws FileException
     * @throws FontException
     */
    public function testImportReportsAnOutOfBoundsReadAsAFontError(): void
    {
        $this->setupTest();
        $outdir = $this->getFontPath();
        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/freefont/';

        $program = \file_get_contents($indir . 'FreeSans.ttf');
        $this->assertIsString($program);

        $corrupt = SubsetMalformedFontTest::corruptCmapSubtableOffsets($program);
        $this->assertNotSame($program, $corrupt, 'the fixture must have been altered');
        \file_put_contents($outdir . 'badcmap.ttf', $corrupt);

        $this->assertThrowsMessage(
            \Com\Tecnick\Pdf\Font\Exception::class,
            'Malformed font program',
            /** @throws \Throwable */
            static fn() => new \Com\Tecnick\Pdf\Font\Import($outdir . 'badcmap.ttf', $outdir),
        );
    }

    /**
     * A file too short to carry a sfnt version must be reported as an undetectable font
     * type rather than read out of bounds.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportRejectsAFileTooShortToDetect(): void
    {
        $outdir = \dirname(__DIR__) . '/target/tmptest/short/';
        \system('rm -rf ' . \escapeshellarg($outdir) . ' && mkdir -p ' . \escapeshellarg($outdir));
        \file_put_contents($outdir . 'tiny.ttf', "\x00\x01");

        $this->assertThrowsMessage(
            \Com\Tecnick\Pdf\Font\Exception::class,
            'too short',
            /** @throws \Throwable */
            static fn() => new \Com\Tecnick\Pdf\Font\Import($outdir . 'tiny.ttf', $outdir),
        );
    }

    /**
     * is_writable() is true for a writable regular file too, which is not a directory and
     * is refused as an output path. A path the caller named explicitly is reported rather
     * than replaced by one of the fallbacks.
     *
     * @throws FileException
     * @throws FontException
     * @throws \ReflectionException
     */
    public function testOutputPathRejectsARegularFile(): void
    {
        $outdir = \dirname(__DIR__) . '/target/tmptest/outpath/';
        \system('rm -rf ' . \escapeshellarg($outdir) . ' && mkdir -p ' . \escapeshellarg($outdir));
        \file_put_contents($outdir . 'notadir', '');

        $class = new \ReflectionClass(\Com\Tecnick\Pdf\Font\Import::class);
        $instance = $class->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($instance, 'findOutputPath');

        $this->assertThrowsMessage(
            \Com\Tecnick\Pdf\Font\Exception::class,
            'The output path is not a writable directory',
            /** @throws \Throwable */
            static fn() => $method->invoke($instance, $outdir . 'notadir'),
        );

        $this->assertSame($outdir, $method->invoke($instance, $outdir));
    }

    /**
     * A path containing a stream wrapper or a parent directory reference is refused too.
     *
     * @throws FileException
     * @throws FontException
     * @throws \ReflectionException
     */
    public function testOutputPathRejectsAnUnsafePath(): void
    {
        $class = new \ReflectionClass(\Com\Tecnick\Pdf\Font\Import::class);
        $instance = $class->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($instance, 'findOutputPath');

        $this->assertThrowsMessage(
            \Com\Tecnick\Pdf\Font\Exception::class,
            'The output path is not a writable directory',
            /** @throws \Throwable */
            static fn() => $method->invoke($instance, \dirname(__DIR__) . '/target/../target/tmptest/'),
        );
    }

    /**
     * A relative input path stored verbatim in the symbolic link would be resolved
     * against the directory of the link, producing a dangling link.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testImportLinkedFontResolvesARelativeInputPath(): void
    {
        $root = \dirname(__DIR__);
        $outdir = $root . '/target/tmptest/linkedrel/';
        \system('rm -rf ' . \escapeshellarg($outdir) . ' && mkdir -p ' . \escapeshellarg($outdir));

        $cwd = \getcwd();
        $this->assertIsString($cwd);
        \chdir($root);

        try {
            $relative = 'util/vendor/tecnickcom/tc-font-mirror/freefont/FreeSans.ttf';
            $import = new \Com\Tecnick\Pdf\Font\Import($relative, $outdir, '', '', 32, 3, 1, true);
            $link = $outdir . $import->getFontMetrics()['file'];

            $this->assertTrue(\is_link($link));
            // the link must resolve to a readable file, not to a path relative to itself
            $this->assertFileExists($link);
        } finally {
            \chdir($cwd);
        }
    }

    /**
     * Drive setFontFile() in linked mode with a controlled font data array.
     *
     * @param array<string, mixed> $fdt
     *
     * @throws \ReflectionException
     */
    private function invokeSetFontFile(array $fdt): void
    {
        $class = new \ReflectionClass(\Com\Tecnick\Pdf\Font\Import\TrueType::class);
        $instance = $class->newInstanceWithoutConstructor();
        // the constructor is bypassed, so the import/subset mode has to be injected too
        (new \ReflectionProperty($instance, 'subsetting'))->setValue($instance, false);
        $fdtProp = new \ReflectionProperty($instance, 'fdt');
        $fdtProp->setValue($instance, \array_replace([
            'desc' => ['MaxWidth' => 0, 'Flags' => 0],
            'type' => 'TrueTypeUnicode',
            'linked' => true,
            'file_name' => 'linkme',
            'Flags' => 0,
        ], $fdt));

        (new \ReflectionMethod($instance, 'setFontFile'))->invoke($instance);
    }

    /**
     * A path that cannot be resolved would be stored verbatim in the link and produce a
     * dangling one.
     *
     * @throws \ReflectionException
     */
    public function testLinkedFontRejectsAnUnresolvableInputPath(): void
    {
        $outdir = \dirname(__DIR__) . '/target/tmptest/linkbad/';
        \system('rm -rf ' . \escapeshellarg($outdir) . ' && mkdir -p ' . \escapeshellarg($outdir));

        $this->assertThrowsMessage(
            \Com\Tecnick\Pdf\Font\Exception::class,
            'unable to resolve the font file',
            /** @throws \Throwable */
            fn() => $this->invokeSetFontFile([
                'input_file' => $outdir . 'missing.ttf',
                'dir' => $outdir,
            ]),
        );
    }

    /**
     * A read-only output directory makes symlink() fail.
     *
     * @throws \ReflectionException
     */
    public function testLinkedFontReportsAFailedSymlink(): void
    {
        if (\function_exists('posix_geteuid') && \posix_geteuid() === 0) {
            $this->markTestSkipped('root ignores the directory permissions');
        }

        $indir = \dirname(__DIR__) . '/util/vendor/tecnickcom/tc-font-mirror/freefont/';
        $outdir = \dirname(__DIR__) . '/target/tmptest/linkro/';
        \system('rm -rf ' . \escapeshellarg($outdir) . ' && mkdir -p ' . \escapeshellarg($outdir));
        \chmod($outdir, 0o555);

        try {
            $this->assertThrowsMessage(
                \Com\Tecnick\Pdf\Font\Exception::class,
                'unable to create the symbolic link',
                /** @throws \Throwable */
                fn() => $this->invokeSetFontFile([
                    'input_file' => $indir . 'FreeSans.ttf',
                    'dir' => $outdir,
                ]),
            );
        } finally {
            \chmod($outdir, 0o755);
        }
    }
}
