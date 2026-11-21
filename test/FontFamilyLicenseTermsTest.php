<?php

/**
 * FontFamilyLicenseTermsTest.php
 *
 * @since     2026-11-19
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

/**
 * Licence terms written by util/bulk_convert.php for each font family.
 *
 * @since     2026-11-19
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FontFamilyLicenseTermsTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/';

    private const GPL3 = "                    GNU GENERAL PUBLIC LICENSE\n                       Version 3, 29 June 2007\n";

    private const EXCEPTION = "As a special exception, if you create a document which\nuses this font, and embed this font into the document.\n";

    private const ADOBE =
        "This file and the 14 PostScript(R) AFM files it accompanies may be used,\n"
            . "provided that the AFM files are not distributed without this file.\n"
            . "Adobe Systems has no responsibility or obligation to support the use of the AFM files.\n";

    /** Input directory with one sub directory per font family. */
    private string $indir = '';

    /** Output directory. */
    private string $outdir = '';

    protected function setUp(): void
    {
        if (!\is_file($this->afm())) {
            $this->markTestSkipped('the tc-font-mirror package is not installed in util/vendor');
        }

        $base = \dirname(__DIR__) . '/target/tmptest/licenseterms/';
        self::resetDirectory($base);
        $this->indir = $base . 'in/';
        $this->outdir = $base . 'out/';
        self::makeDirectory($this->indir);
    }

    private function afm(): string
    {
        return \dirname(__DIR__) . self::MIRROR . 'core/Helvetica.afm';
    }

    /**
     * Create a font family input directory holding one font file and the given licence files.
     *
     * @param string $font Font file to copy, default core/Helvetica.afm from the mirror.
     */
    private function makeFamily(string $name, ?string $license, ?string $notice = null, string $font = ''): void
    {
        $dir = $this->indir . $name . '/';
        self::makeDirectory($dir);
        $font = $font === '' ? $this->afm() : $font;
        \copy($font, $dir . \basename($font));
        if ($license !== null) {
            \file_put_contents($dir . 'LICENSE', $license);
        }

        if ($notice !== null) {
            \file_put_contents($dir . 'NOTICE', $notice);
        }
    }

    /**
     * Run bulk_convert.php on the input directory.
     *
     * @return array{int, string} Exit code and standard error output.
     */
    private function runBulkConvert(): array
    {
        $cmd = [
            PHP_BINARY,
            \dirname(__DIR__) . '/util/bulk_convert.php',
            '--indir=' . $this->indir,
            '--outpath=' . $this->outdir,
        ];
        $base = \dirname($this->indir) . '/';
        $pipes = [];
        $proc = \proc_open(
            $cmd,
            [1 => ['file', $base . 'stdout.txt', 'w'], 2 => ['file', $base . 'stderr.txt', 'w']],
            $pipes,
        );
        $this->assertIsResource($proc);
        $code = \proc_close($proc);

        return [$code, (string) \file_get_contents($base . 'stderr.txt')];
    }

    private function readOutput(string $file): string
    {
        $this->assertFileExists($this->outdir . $file);

        return (string) \file_get_contents($this->outdir . $file);
    }

    public function testBlankLicenseSkipsTheFamily(): void
    {
        $this->makeFamily('core', " \n\t\n");

        [$code, $stderr] = $this->runBulkConvert();

        $this->assertSame(9, $code);
        $this->assertStringContainsString('empty', $stderr);
        $this->assertDirectoryDoesNotExist($this->outdir . 'core');
    }

    public function testMissingLicenseSkipsTheFamily(): void
    {
        $this->makeFamily('core', null);

        [$code, $stderr] = $this->runBulkConvert();

        $this->assertSame(9, $code);
        $this->assertStringContainsString('missing', $stderr);
        $this->assertDirectoryDoesNotExist($this->outdir . 'core');
    }

    public function testBlankNoticeSkipsTheFamily(): void
    {
        $this->makeFamily('freefont', self::GPL3, "\n");

        [$code, $stderr] = $this->runBulkConvert();

        $this->assertSame(9, $code);
        $this->assertStringContainsString('NOTICE', $stderr);
        $this->assertDirectoryDoesNotExist($this->outdir . 'freefont');
    }

    public function testLicenseWithoutTheEmbeddingExceptionSkipsTheFamily(): void
    {
        $this->makeFamily('freefont', self::GPL3);

        [$code, $stderr] = $this->runBulkConvert();

        $this->assertSame(9, $code);
        $this->assertStringContainsString('do not contain: As a special exception', $stderr);
        $this->assertDirectoryDoesNotExist($this->outdir . 'freefont');
    }

    public function testNoticeIsWrittenBeforeTheLicenseText(): void
    {
        $this->makeFamily('freefont', self::GPL3, self::EXCEPTION);

        [$code] = $this->runBulkConvert();

        $this->assertSame(0, $code);
        $terms = $this->readOutput('freefont/LICENSE');
        $this->assertStringStartsWith(self::EXCEPTION, $terms);
        $this->assertStringEndsWith(self::GPL3, $terms);
    }

    public function testReadmeStatesTheSpdxExpressionAndTheEmbeddingException(): void
    {
        $this->makeFamily('freefont', self::GPL3, self::EXCEPTION);

        [$code] = $this->runBulkConvert();

        $this->assertSame(0, $code);
        $readme = $this->readOutput('freefont/README');
        $this->assertStringContainsString(
            "\nSPDX-License-Identifier: GPL-3.0-or-later WITH Font-exception-2.0\n",
            $readme,
        );
        $this->assertStringContainsString('GNU font embedding exception', $readme);
    }

    public function testCoreLicenseCarriesTheAdobeNotice(): void
    {
        $this->makeFamily('core', self::ADOBE);

        [$code] = $this->runBulkConvert();

        $this->assertSame(0, $code);
        $this->assertSame(self::ADOBE, $this->readOutput('core/LICENSE'));
        $readme = $this->readOutput('core/README');
        $this->assertStringContainsString("\nSPDX-License-Identifier: APAFML\n", $readme);
        $this->assertStringNotContainsString('embedding exception', $readme);
    }

    public function testUnlistedFamilyNeedsOnlyANonEmptyLicense(): void
    {
        $this->makeFamily('custom', "Custom terms.\n");

        [$code, $stderr] = $this->runBulkConvert();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('no licence metadata for the custom family', $stderr);
        $this->assertSame("Custom terms.\n", $this->readOutput('custom/LICENSE'));
        $this->assertStringContainsString('SPDX-License-Identifier: NOASSERTION', $this->readOutput('custom/README'));
    }

    public function testOtherFamiliesAreConvertedWhenOneIsSkipped(): void
    {
        $this->makeFamily('core', '');
        $this->makeFamily('custom', "Custom terms.\n");

        [$code] = $this->runBulkConvert();

        $this->assertSame(9, $code);
        $this->assertDirectoryDoesNotExist($this->outdir . 'core');
        $this->assertFileExists($this->outdir . 'custom/helvetica.json');
    }

    public function testMirrorFamiliesCarryCompleteTerms(): void
    {
        $mirror = \dirname(__DIR__) . self::MIRROR;
        $expected = [
            'cid0' => 'GPL-2.0-or-later WITH Font-exception-2.0',
            'core' => 'APAFML',
            'dejavu' => 'Bitstream-Vera AND LicenseRef-Arev',
            'freefont' => 'GPL-3.0-or-later WITH Font-exception-2.0',
            'pdfa' => 'GPL-3.0-or-later WITH Font-exception-2.0',
            'unifont' => 'GPL-2.0-or-later WITH Font-exception-2.0 OR OFL-1.1',
        ];
        foreach (\array_keys($expected) as $family) {
            $license = \file_get_contents($mirror . $family . '/LICENSE');
            $this->assertIsString($license);
            $notice = \is_file($mirror . $family . '/NOTICE')
                ? \file_get_contents($mirror . $family . '/NOTICE')
                : null;
            // cid0 takes the font type from the file name
            $font = $family === 'cid0' ? $mirror . 'cid0/cid0cs.ttf' : '';
            $this->makeFamily($family, $license, $notice === false ? null : $notice, $font);
        }

        [$code, $stderr] = $this->runBulkConvert();

        $this->assertSame(0, $code, $stderr);
        foreach ($expected as $family => $spdx) {
            $this->assertStringContainsString(
                "\nSPDX-License-Identifier: " . $spdx . "\n",
                $this->readOutput($family . '/README'),
            );
        }

        $this->assertStringStartsWith('GNU FreeFont', $this->readOutput('freefont/LICENSE'));
        $this->assertStringContainsString('is extended to these fonts', $this->readOutput('pdfa/LICENSE'));
    }
}
