<?php

/**
 * SubsetCharAggregationTest.php
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
use Com\Tecnick\Pdf\Font\Stack;
use Com\Tecnick\Pdf\Font\Zlib;

/**
 * Collection of the characters a font program is subset to.
 *
 * A program shared by several fonts is emitted once, so the characters used by all of them
 * are collected together, and only the enabled entries are kept.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class SubsetCharAggregationTest extends TestUtil
{
    /**
     * Write a TrueType definition and its font program, and return the loaded font key.
     *
     * @throws \Throwable
     */
    private function writeFont(string $key): void
    {
        $path = $this->getFontPath();
        \file_put_contents($path . $key . '.z', Zlib::compress(
            \str_repeat('P', 128),
            'unable to compress the test program',
        ));

        \file_put_contents(
            $path . $key . '.json',
            (string) \json_encode([
                'type' => 'TrueType',
                'name' => \ucfirst($key),
                'file' => $key . '.z',
                'originalsize' => 128,
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
            ]),
        );
    }

    /**
     * @throws \Throwable
     */
    public function testDisabledSubsetCharsAreLeftOutWhicheverFontDeclaresThem(): void
    {
        $this->setupTest();
        $this->writeFont('subchara');

        $stack = new Stack(1);
        $objnum = 1;
        $stack->add($objnum, 'subchara');

        $fonts = $stack->getFonts();
        // every font of the program declares the same map, with one entry disabled
        foreach ($fonts as $fkey => $font) {
            $font['subsetchars'] = [
                65 => true,
                66 => false,
            ];
            $fonts[$fkey] = $font;
        }

        $reflector = new \ReflectionClass(Encrypt::class);
        $encrypt = $reflector->newInstanceWithoutConstructor();
        \assert($encrypt instanceof Encrypt, 'Failed to create Encrypt instance');

        $output = new OutputTestOutput($fonts, $objnum, $encrypt, null);

        $collected = $output->getSubsetChars();
        $this->assertCount(1, $collected, 'one font program');
        $this->assertSame([65 => true], \array_values($collected)[0] ?? []);
    }
}
