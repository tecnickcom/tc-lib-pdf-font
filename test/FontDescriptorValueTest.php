<?php

/**
 * FontDescriptorValueTest.php
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

/**
 * Emission of the entries of a font descriptor.
 *
 * The font definition file is merged into the font data as it is decoded, so an entry of
 * the descriptor may carry any JSON value. Only a number or a non empty string has a form
 * in the dictionary: everything else is dropped, as a key left without a value would take
 * the name of the next one as its value.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FontDescriptorValueTest extends TestUtil
{
    public function testANumericValueIsWrittenAsANumber(): void
    {
        $outfont = new OutputTestOutFont();

        $this->assertSame(' /Flags 32', $outfont->runGetKeyValOut('Flags', 32));
        $this->assertSame(' /ItalicAngle -11.000000', $outfont->runGetKeyValOut('ItalicAngle', -11.0));
    }

    public function testAStringValueIsWrittenAsItIs(): void
    {
        $outfont = new OutputTestOutFont();

        $this->assertSame(' /FontBBox [0 -218 998 917]', $outfont->runGetKeyValOut('FontBBox', '[0 -218 998 917]'));
    }

    /**
     * An array has no form in the dictionary, and casting it would emit the literal
     * 'Array' along with a PHP warning.
     */
    public function testAnArrayValueIsDropped(): void
    {
        $outfont = new OutputTestOutFont();

        $this->assertSame('', $outfont->runGetKeyValOut('Flags', [1, 2]));
    }

    /**
     * A boolean, a null or an empty string would leave the key without a value, so the
     * name of the next key would be read as the value of this one.
     */
    public function testAValueWithoutAPdfFormIsDropped(): void
    {
        $outfont = new OutputTestOutFont();

        $this->assertSame('', $outfont->runGetKeyValOut('Flags', false));
        $this->assertSame('', $outfont->runGetKeyValOut('Flags', true));
        $this->assertSame('', $outfont->runGetKeyValOut('Flags', null));
        $this->assertSame('', $outfont->runGetKeyValOut('Flags', ''));
    }

    /**
     * @throws \Throwable
     */
    public function testADescriptorEntryWithoutAPdfFormDoesNotShiftTheNextKey(): void
    {
        $this->setupTest();

        $path = $this->getFontPath();
        $definition = [
            'type' => 'Type1',
            'name' => 'BrokenDesc',
            'enc' => 'cp1252',
            'dw' => 600,
            'cw' => [
                32 => 250,
            ],
            'desc' => [
                'Flags' => null,
                'FontBBox' => '[0 0 1000 1000]',
                'ItalicAngle' => 0,
                'Ascent' => 800,
                'Descent' => -200,
                'CapHeight' => 700,
                'MissingWidth' => 600,
            ],
        ];
        \file_put_contents($path . 'brokendesc.json', (string) \json_encode($definition));

        $stack = new \Com\Tecnick\Pdf\Font\Stack(1);
        $objnum = 1;
        $stack->insert($objnum, 'brokendesc');

        $reflector = new \ReflectionClass(\Com\Tecnick\Pdf\Encrypt\Encrypt::class);
        $encrypt = $reflector->newInstanceWithoutConstructor();
        \assert($encrypt instanceof \Com\Tecnick\Pdf\Encrypt\Encrypt, 'Failed to create Encrypt instance');

        $output = new \Com\Tecnick\Pdf\Font\Output($stack->getFonts(), $objnum, $encrypt, null);
        $block = $output->getFontsBlock();

        // the key is dropped: keeping it would write '/Flags  /FontBBox', so the box would
        // be read as the value of the flags
        $this->assertStringNotContainsString('/Flags', $block);
        $this->assertStringContainsString('/FontBBox [0 0 1000 1000]', $block);
        $this->assertStringContainsString('/Ascent 800', $block);
    }
}
