<?php

/**
 * SubsetChecksumTest.php
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
use Com\Tecnick\Pdf\Font\FontPaths;
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Subset;

/**
 * Every table checksum of a subset font program is computed from the bytes it emits, so a
 * font shipping a checksum that does not match its own table does not propagate it, and the
 * edited 'head' table carries a checksum of its edited form.
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
class SubsetChecksumTest extends TestUtil
{
    private const MIRROR = 'util/vendor/tecnickcom/tc-font-mirror/freefont/';

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
        $indir = \dirname(__DIR__) . '/' . self::MIRROR;
        $outdir = $this->getFontPath();
        new Import($indir . 'FreeSans.ttf', $outdir);

        $definition = \file_get_contents($outdir . 'freesans.json');
        $this->assertIsString($definition);
        /** @var array<array-key, mixed> $decoded */
        $decoded = \json_decode($definition, true);

        $program = \file_get_contents($indir . 'FreeSans.ttf');
        $this->assertIsString($program);

        $ref = new \ReflectionClass(Subset::class);
        /** @var TFontData $template */
        $template = $ref->getProperty('fdt')->getValue($ref->newInstanceWithoutConstructor());

        /** @var TFontData $fdt */
        $fdt = \array_replace_recursive($template, $decoded);

        return [$fdt, $program];
    }

    private function uint32(string $data, int $offset): int
    {
        return $this->readBigEndian($data, $offset, 'N');
    }

    private function uint16(string $data, int $offset): int
    {
        return $this->readBigEndian($data, $offset, 'n');
    }

    /**
     * Read a big-endian uint16 ('n') or uint32 ('N').
     */
    private function readBigEndian(string $data, int $offset, string $format): int
    {
        $size = $format === 'N' ? 4 : 2;
        $value = \unpack($format . 'value', \substr($data, $offset, $size));
        $this->assertIsArray($value);

        return (int) ($value['value'] ?? 0);
    }

    /**
     * The sfnt checksum of a run of bytes: the sum of its 32-bit words, the trailing
     * partial one zero padded, truncated to 32 bits.
     */
    private function checksum(string $data): int
    {
        $data = \str_pad($data, (int) ((\strlen($data) + 3) / 4) * 4, "\x00");
        $sum = 0;
        for ($idx = 0; $idx < \strlen($data); $idx += 4) {
            $sum = ($sum + $this->uint32($data, $idx)) & 0xFFFF_FFFF;
        }

        return $sum;
    }

    /**
     * @throws \Throwable
     */
    private function subsetOf(string $program): string
    {
        [$fdt] = $this->importFreeSans();
        $fileHelper = new ObjFile(allowedPaths: FontPaths::buildAllowedPaths());

        return (new Subset($program, $fdt, $fileHelper, [65 => true, 66 => true]))->getSubsetFont();
    }

    /**
     * Corrupt the directory checksum a font declares for one of its tables.
     */
    private function withBrokenChecksum(string $program, string $tag): string
    {
        $num = $this->uint16($program, 4);
        for ($idx = 0; $idx < $num; ++$idx) {
            $record = 12 + ($idx * 16);
            if (\substr($program, $record, 4) !== $tag) {
                continue;
            }

            return \substr_replace($program, \pack('N', 0xDEAD_BEEF), $record + 4, 4);
        }

        $this->fail('the fixture must carry a ' . $tag . ' table');
    }

    /**
     * Every checksum of the emitted directory matches the bytes the subset carries.
     *
     * @throws \Throwable
     */
    public function testEveryEmittedTableChecksumMatchesItsData(): void
    {
        [, $program] = $this->importFreeSans();
        $subset = $this->subsetOf($program);

        $num = $this->uint16($subset, 4);
        $this->assertGreaterThan(0, $num);
        for ($idx = 0; $idx < $num; ++$idx) {
            $record = 12 + ($idx * 16);
            $tag = \substr($subset, $record, 4);
            $declared = $this->uint32($subset, $record + 4);
            $offset = $this->uint32($subset, $record + 8);
            $length = $this->uint32($subset, $record + 12);
            $data = \substr($subset, $offset, $length);
            if ($tag === 'head') {
                // the checksum of this table is the one of its form with a zero
                // checkSumAdjustment, which the adjustment written into it completes
                $data = \substr_replace($data, "\x00\x00\x00\x00", 8, 4);
            }

            $this->assertSame($this->checksum($data), $declared, 'checksum of the ' . $tag . ' table');
        }
    }

    /**
     * A wrong checksum in the directory of the input font is not copied into the subset.
     *
     * @throws \Throwable
     */
    public function testAWrongInputChecksumIsNotPropagated(): void
    {
        [, $program] = $this->importFreeSans();
        $subset = $this->subsetOf($this->withBrokenChecksum($program, 'maxp'));

        $num = $this->uint16($subset, 4);
        for ($idx = 0; $idx < $num; ++$idx) {
            $record = 12 + ($idx * 16);
            if (\substr($subset, $record, 4) !== 'maxp') {
                continue;
            }

            $offset = $this->uint32($subset, $record + 8);
            $length = $this->uint32($subset, $record + 12);
            $this->assertNotSame(0xDEAD_BEEF, $this->uint32($subset, $record + 4));
            $this->assertSame($this->checksum(\substr($subset, $offset, $length)), $this->uint32($subset, $record + 4));

            return;
        }

        $this->fail('the subset must carry a maxp table');
    }

    /**
     * The 'head' checksum covers the table as emitted, whose checkSumAdjustment is zero,
     * and the adjustment itself completes the checksum of the whole font.
     *
     * @throws \Throwable
     */
    public function testTheHeadAdjustmentCompletesTheChecksumOfTheWholeFont(): void
    {
        [, $program] = $this->importFreeSans();
        $subset = $this->subsetOf($program);

        $num = $this->uint16($subset, 4);
        $headOffset = 0;
        for ($idx = 0; $idx < $num; ++$idx) {
            $record = 12 + ($idx * 16);
            if (\substr($subset, $record, 4) === 'head') {
                $headOffset = $this->uint32($subset, $record + 8);
            }
        }

        $this->assertGreaterThan(0, $headOffset);
        $zeroed = \substr_replace($subset, "\x00\x00\x00\x00", $headOffset + 8, 4);

        $this->assertSame(
            (0xB1B0_AFBA - $this->checksum($zeroed)) & 0xFFFF_FFFF,
            $this->uint32($subset, $headOffset + 8),
        );
    }
}
