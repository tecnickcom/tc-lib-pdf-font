<?php

/**
 * TrueTypeItalicFlagTest.php
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

use Com\Tecnick\Pdf\Font\Import;

/**
 * The italic descriptor bit of a TrueType font is settled by the font program, not by the
 * guess Import::initFlags() makes from the file name.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TrueTypeItalicFlagTest extends TestUtil
{
    /** Bit 7 of the PDF font descriptor flags (ISO 32000-1 Table 123). */
    private const ITALIC = 64;

    private const MIRROR = 'util/vendor/tecnickcom/tc-font-mirror/freefont/';

    private function mirror(string $file): string
    {
        return \dirname(__DIR__) . '/' . self::MIRROR . $file;
    }

    private function readProgram(string $file): string
    {
        $program = \file_get_contents($this->mirror($file));
        $this->assertIsString($program);

        return $program;
    }

    /**
     * Read a big-endian uint16 ('n') or uint32 ('N') out of a font program.
     */
    private function readBigEndian(string $data, int $offset, string $format): int
    {
        $size = $format === 'N' ? 4 : 2;
        $value = \unpack($format . 'value', \substr($data, $offset, $size));
        $this->assertIsArray($value);

        return (int) ($value['value'] ?? 0);
    }

    /**
     * Returns the offset of a table in the directory of a font program.
     */
    private function tableOffset(string $program, string $tag): int
    {
        $num = $this->readBigEndian($program, 4, 'n');
        for ($idx = 0; $idx < $num; ++$idx) {
            $record = 12 + ($idx * 16);
            if (\substr($program, $record, 4) !== $tag) {
                continue;
            }

            return $this->readBigEndian($program, $record + 8, 'N');
        }

        $this->fail('the fixture must carry a ' . $tag . ' table');
    }

    /**
     * Import a font program stored under the given name and return its descriptor flags.
     *
     * @throws \Throwable
     */
    private function importedFlags(string $program, string $name): int
    {
        $dir = $this->getFontPath();
        \file_put_contents($dir . $name, $program);
        $import = new Import($dir . $name, $dir);

        return $import->getFontMetrics()['Flags'];
    }

    /**
     * The file name says italic, the 'head' table of the program says upright: the program
     * decides, as it already does for the fixed pitch bit.
     *
     * @throws \Throwable
     */
    public function testAnUprightProgramNamedItalicIsNotItalic(): void
    {
        $this->setupTest();
        $flags = $this->importedFlags($this->readProgram('FreeSans.ttf'), 'notreallyitalic.ttf');

        $this->assertSame(0, $flags & self::ITALIC);
    }

    /**
     * A file name with no hint of a style does not make an italic program upright either.
     *
     * @throws \Throwable
     */
    public function testASlantedProgramIsItalicWhateverItsName(): void
    {
        $this->setupTest();
        $flags = $this->importedFlags($this->readProgram('FreeSansOblique.ttf'), 'plainname.ttf');

        $this->assertSame(self::ITALIC, $flags & self::ITALIC);
    }

    /**
     * A slanted program whose 'head.macStyle' does not admit it is still italic: the
     * 'post.italicAngle' of the same program settles it.
     *
     * @throws \Throwable
     */
    public function testASlantedProgramIsItalicEvenWithAnUprightMacStyle(): void
    {
        $this->setupTest();
        $program = $this->readProgram('FreeSansOblique.ttf');

        // macStyle is the uint16 at offset 44 of the 'head' table
        $macStylePos = $this->tableOffset($program, 'head') + 44;
        $macStyle = $this->readBigEndian($program, $macStylePos, 'n');
        $this->assertSame(2, $macStyle & 2, 'the fixture must declare itself italic');

        $upright = \substr_replace($program, \pack('n', $macStyle & ~2), $macStylePos, 2);

        $this->assertSame(self::ITALIC, $this->importedFlags($upright, 'uprightmacstyle.ttf') & self::ITALIC);
    }
}
