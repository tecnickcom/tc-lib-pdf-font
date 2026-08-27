<?php

/**
 * CidFont0WidthsTest.php
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

/**
 * The /W array of a CID-0 font.
 *
 * A CID-0 font is not embedded, so there is no program to subset and the subset flag is
 * cleared. Its widths are emitted by CID.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class CidFont0WidthsTest extends TestUtil
{
    /**
     * Write the definition of a CID-0 font mapping U+4E00 to CID 1000, 500 units wide.
     */
    private function writeDefinition(): void
    {
        $definition = [
            'type' => 'cidfont0',
            'name' => 'KozMinPro-Regular',
            'dw' => 1000,
            'enc' => 'UniJIS-UCS2-H',
            'cidinfo' => [
                'Registry' => 'Adobe',
                'Ordering' => 'Japan1',
                'Supplement' => 5,
                'uni2cid' => [
                    19968 => 1000,
                ],
            ],
            'cw' => [
                32 => 250,
                19968 => 500,
            ],
            'desc' => [
                'Flags' => 4,
                'FontBBox' => '[0 -120 1000 880]',
                'ItalicAngle' => 0,
                'Ascent' => 880,
                'Descent' => -120,
                'Leading' => 0,
                'CapHeight' => 700,
                'XHeight' => 500,
                'StemV' => 80,
                'StemH' => 20,
                'AvgWidth' => 500,
                'MaxWidth' => 1000,
                'MissingWidth' => 1000,
            ],
        ];

        \file_put_contents($this->getFontPath() . 'kozmin.json', (string) \json_encode($definition));
    }

    /**
     * @throws \Throwable
     */
    public function testCidFont0IsNeverSubset(): void
    {
        $this->setupTest();
        $this->writeDefinition();

        $stack = new Stack(1, true);
        $objnum = 1;
        $stack->insert($objnum, 'kozmin');

        $this->assertTrue($stack->isSubsetMode(), 'the stack was built in subset mode');
        $this->assertFalse($stack->getFont('kozmin')['subset']);
    }

    /**
     * @throws \Throwable
     */
    public function testCidFont0KeepsTheWidthOfTheUsedGlyphsInSubsetMode(): void
    {
        $this->setupTest();
        $this->writeDefinition();

        $stack = new Stack(1, true);
        $objnum = 1;
        $stack->insert($objnum, 'kozmin');
        $stack->addSubsetChar('kozmin', 19968);

        $reflector = new \ReflectionClass(Encrypt::class);
        $encrypt = $reflector->newInstanceWithoutConstructor();
        \assert($encrypt instanceof Encrypt, 'Failed to create Encrypt instance');

        $output = new Output($stack->getFonts(), $objnum, $encrypt, null);
        $block = $output->getFontsBlock();

        // the widths are emitted by CID, offset by 31 because CID 1 is not defined
        $this->assertMatchesRegularExpression('#/W \[[^]]*\b1000 1000 500\b#', $block);
    }
}
