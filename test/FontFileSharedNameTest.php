<?php

/**
 * FontFileSharedNameTest.php
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

use Com\Tecnick\Pdf\Encrypt\Encrypt;
use Com\Tecnick\Pdf\Font\Output;
use Com\Tecnick\Pdf\Font\Stack;
use Com\Tecnick\Pdf\Font\Zlib;

/**
 * Emission of font programs that share a file name.
 *
 * The program of each font is emitted once and referenced by every font backed by it, and
 * the directory takes part in the identity of the file.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FontFileSharedNameTest extends TestUtil
{
    /**
     * Write a TrueType definition and its font program in a sub-directory of the font path.
     *
     * @throws \Throwable
     */
    private function writeFont(string $dir, string $key, string $program): void
    {
        $path = $this->getFontPath() . $dir;
        $this->assertTrue(\is_dir($path) || \mkdir($path, 0o777, true));

        \file_put_contents($path . DIRECTORY_SEPARATOR . 'shared.z', Zlib::compress(
            $program,
            'unable to compress the test program',
        ));

        $definition = [
            'type' => 'TrueType',
            'name' => \ucfirst($key),
            'file' => 'shared.z',
            'originalsize' => \strlen($program),
            'enc' => 'cp1252',
            'dw' => 600,
            'cw' => [
                32 => 250,
            ],
            'desc' => [
                'Flags' => 32,
                'FontBBox' => '[0 0 1000 1000]',
                'ItalicAngle' => 0,
                'Ascent' => 800,
                'Descent' => -200,
                'Leading' => 0,
                'CapHeight' => 700,
                'XHeight' => 500,
                'StemV' => 80,
                'StemH' => 20,
                'AvgWidth' => 500,
                'MaxWidth' => 1000,
                'MissingWidth' => 600,
            ],
        ];

        \file_put_contents($path . DIRECTORY_SEPARATOR . $key . '.json', (string) \json_encode($definition));
    }

    /**
     * @throws \Throwable
     */
    public function testFontsWithTheSameFileNameInDifferentDirectoriesKeepTheirOwnProgram(): void
    {
        $this->setupTest();
        $this->writeFont('one', 'fonta', \str_repeat('A', 128));
        $this->writeFont('two', 'fontb', \str_repeat('B', 256));

        $stack = new Stack(1);
        $objnum = 1;
        $stack->insert($objnum, 'fonta');
        $stack->insert($objnum, 'fontb');

        $reflector = new \ReflectionClass(Encrypt::class);
        $encrypt = $reflector->newInstanceWithoutConstructor();
        \assert($encrypt instanceof Encrypt, 'Failed to create Encrypt instance');

        $output = new Output($stack->getFonts(), $objnum, $encrypt, null);
        $block = $output->getFontsBlock();

        $refs = [];
        \preg_match_all('#/FontFile2 (\d+) 0 R#', $block, $refs);
        $streams = $refs[1] ?? [];
        $this->assertIsArray($streams);
        $this->assertCount(2, $streams, 'both fonts declare an embedded program');
        $this->assertNotSame($streams[0] ?? null, $streams[1] ?? null, 'each font references its own stream');
    }
}
