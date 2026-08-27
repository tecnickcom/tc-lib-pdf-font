<?php

/**
 * SubsetLicenseTest.php
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
use Com\Tecnick\Pdf\Encrypt\Encrypt;
use Com\Tecnick\Pdf\Font\FontPaths;
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Output;
use Com\Tecnick\Pdf\Font\Stack;
use Com\Tecnick\Pdf\Font\Subset;

/**
 * Subsetting of a font whose license does not allow it.
 *
 * Bit 8 of the OS/2 fsType field ("No Subsetting") permits embedding but forbids emitting
 * a reduced program, so the whole font is embedded.
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
class SubsetLicenseTest extends TestUtil
{
    private const MIRROR = 'util/vendor/tecnickcom/tc-font-mirror/freefont/';

    /**
     * Return the offset of a table in the directory of a TrueType program.
     *
     * @throws \Throwable
     */
    private function findTableOffset(string $program, string $tag): int
    {
        $header = \unpack('nnum', \substr($program, 4, 2));
        $this->assertIsArray($header);
        $numTables = (int) ($header['num'] ?? 0);
        for ($idx = 0; $idx < $numTables; ++$idx) {
            $record = 12 + ($idx * 16);
            if (\substr($program, $record, 4) === $tag) {
                $offset = \unpack('Noff', \substr($program, $record + 8, 4));
                $this->assertIsArray($offset);
                return (int) ($offset['off'] ?? 0);
            }
        }

        $this->fail('the ' . $tag . ' table is missing from the test font');
    }

    /**
     * Load the definition and the program of the bundled FreeSans font.
     *
     * @return array{0: TFontData, 1: string}
     *
     * @throws \Throwable
     */
    private function importFreeSans(): array
    {
        $this->setupTest();
        $indir = \dirname(__DIR__) . '/' . self::MIRROR;
        $outdir = $this->getFontPath();
        new Import($indir . 'FreeSans.ttf', $outdir);

        $definition = \file_get_contents($outdir . 'freesans.json');
        $this->assertIsString($definition);
        /** @var array<array-key, mixed> $decoded */
        $decoded = \json_decode($definition, true);

        $ref = new \ReflectionClass(Subset::class);
        /** @var TFontData $template */
        $template = $ref->getProperty('fdt')->getValue($ref->newInstanceWithoutConstructor());

        $program = \file_get_contents($indir . 'FreeSans.ttf');
        $this->assertIsString($program);

        /** @var TFontData $fdt */
        $fdt = \array_replace_recursive($template, $decoded);
        $fdt['subset'] = true;

        return [$fdt, $program];
    }

    /**
     * @throws \Throwable
     */
    public function testANoSubsettingProgramIsEmbeddedInFull(): void
    {
        [$fdt, $program] = $this->importFreeSans();
        $fileHelper = new ObjFile(allowedPaths: FontPaths::buildAllowedPaths());

        $allowed = new Subset($program, $fdt, $fileHelper, [65 => true]);
        $this->assertLessThan(
            \strlen($program),
            \strlen($allowed->getSubsetFont()),
            'the unrestricted font is reduced',
        );

        // fsType is the fifth uint16 of the OS/2 table
        $fsTypeAt = $this->findTableOffset($program, 'OS/2') + 8;
        $restricted = \substr_replace($program, "\x01\x00", $fsTypeAt, 2);

        $subset = new Subset($restricted, $fdt, $fileHelper, [65 => true]);
        $this->assertSame(
            $restricted,
            $subset->getSubsetFont(),
            'a program that forbids subsetting is returned untouched',
        );
    }

    /**
     * The six letter tag ISO 32000-1 9.6.4 reserves for a reduced program must not be
     * emitted for a font that is embedded whole because its license forbids subsetting.
     *
     * @throws \Throwable
     */
    public function testANoSubsettingProgramIsNamedWithoutTheSubsetTag(): void
    {
        $this->setupTest();
        $indir = \dirname(__DIR__) . '/' . self::MIRROR;
        $outdir = $this->getFontPath();

        $program = \file_get_contents($indir . 'FreeSans.ttf');
        $this->assertIsString($program);
        // fsType is the fifth uint16 of the OS/2 table
        $fsTypeAt = $this->findTableOffset($program, 'OS/2') + 8;
        $source = $outdir . 'nosubsetsans.ttf';
        \file_put_contents($source, \substr_replace($program, "\x01\x00", $fsTypeAt, 2));

        new Import($source, $outdir);

        $objnum = 1;
        $stack = new Stack(1, true);
        $stack->insert($objnum, 'nosubsetsans');
        $stack->ordArrToGidStr([72, 101, 108, 108, 111]);

        $reflector = new \ReflectionClass(Encrypt::class);
        $encrypt = $reflector->newInstanceWithoutConstructor();
        \assert($encrypt instanceof Encrypt, 'the Encrypt stub must be usable');

        $output = new Output($stack->getFonts(), $objnum, $encrypt, null);
        $block = $output->getFontsBlock();

        $this->assertStringContainsString('/BaseFont /FreeSans', $block);
        $this->assertStringNotContainsString('+FreeSans', $block);
        // the whole program is embedded, so the stream declares its full length
        $this->assertStringContainsString('/Length1 ' . \strlen($program), $block);
    }

    /**
     * A program that does allow subsetting keeps the tag, so that the assertion above
     * reports the licensing bit and not the absence of subsetting altogether.
     *
     * @throws \Throwable
     */
    public function testASubsetProgramIsNamedWithTheSubsetTag(): void
    {
        $this->setupTest();
        $indir = \dirname(__DIR__) . '/' . self::MIRROR;
        $outdir = $this->getFontPath();

        new Import($indir . 'FreeSans.ttf', $outdir);

        $objnum = 1;
        $stack = new Stack(1, true);
        $stack->insert($objnum, 'freesans');
        $stack->ordArrToGidStr([72, 101, 108, 108, 111]);

        $reflector = new \ReflectionClass(Encrypt::class);
        $encrypt = $reflector->newInstanceWithoutConstructor();
        \assert($encrypt instanceof Encrypt, 'the Encrypt stub must be usable');

        $output = new Output($stack->getFonts(), $objnum, $encrypt, null);

        $this->assertMatchesRegularExpression('|/BaseFont /[A-J]{6}\+FreeSans|', $output->getFontsBlock());
    }
}
