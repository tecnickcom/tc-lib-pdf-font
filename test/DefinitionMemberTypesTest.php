<?php

/**
 * DefinitionMemberTypesTest.php
 *
 * @since     2026-08-27
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
use Com\Tecnick\Pdf\Encrypt\Encrypt;
use Com\Tecnick\Pdf\Encrypt\Exception as EncException;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Output;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * Reading the members of a font definition file at the types the font data declares.
 *
 * A member may carry any JSON value, and is read at the type of its entry in the font data.
 * One that cannot be read that way is dropped and the default applies.
 *
 * @since     2026-08-27
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class DefinitionMemberTypesTest extends TestUtil
{
    /**
     * Write a definition file and insert it into a new stack.
     *
     * The size is 1000 and the stretching is 1, so the scaled metrics are the declared ones.
     *
     * @param string $key     Font key, which is also the name of the file.
     * @param string $members Members of the definition file, without the enclosing braces.
     * @param string $style   Font style to request.
     *
     * @throws FontException
     */
    private function stackWith(string $key, string $members, string $style = ''): Stack
    {
        \file_put_contents($this->getFontPath() . $key . '.json', '{' . $members . '}');

        $stack = new Stack(1);
        $objnum = 1;
        $stack->insert($objnum, $key, $style, 1000, 0, 1);

        return $stack;
    }

    /**
     * Returns the PDF fonts block of the fonts of a stack.
     *
     * @throws EncException
     * @throws FileException
     * @throws FontException
     */
    private function fontsBlock(Stack $stack): string
    {
        return (new Output($stack->getFonts(), 100, new Encrypt()))->getFontsBlock();
    }

    /**
     * The bounding box is written as a PDF array literal, and a file that spells it as the
     * array of numbers it describes is read the same way.
     *
     * @throws \Throwable
     */
    public function testAFontBBoxSpelledAsAnArrayIsMeasuredLikeTheLiteral(): void
    {
        $this->setupTest();
        $stack = $this->stackWith(
            'arraybox',
            '"type":"TrueType","name":"arraybox","dw":600,"desc":{"FontBBox":[-100,-200,1000,900],"Ascent":900}',
        );

        $this->bcAssertEqualsWithDelta([-100.0, -200.0, 1000.0, 900.0], $stack->getCurrentFont()['fbbox'], 0.0001);
        $this->assertStringContainsString('/FontBBox [-100 -200 1000 900]', $this->fontsBlock($stack));
    }

    /**
     * A bounding box that is neither the literal nor an array of numbers describes no box,
     * so the descriptor is written without it rather than with whatever the file spells.
     *
     * @throws \Throwable
     */
    public function testAFontBBoxThatIsNotABoxIsDropped(): void
    {
        $this->setupTest();
        $stack = $this->stackWith('numberbox', '"type":"TrueType","name":"numberbox","dw":600,"desc":{"FontBBox":12}');

        $this->bcAssertEqualsWithDelta([0.0, 0.0, 0.0, 0.0], $stack->getCurrentFont()['fbbox'], 0.0001);
        $this->assertStringNotContainsString('/FontBBox', $this->fontsBlock($stack));
    }

    /**
     * A width map that is not a map carries no width, so every character falls back to the
     * default width.
     *
     * @throws \Throwable
     */
    public function testAWidthMapThatIsNotAMapIsDropped(): void
    {
        $this->setupTest();
        $stack = $this->stackWith('flatwidths', '"type":"Core","name":"flatwidths","dw":600,"cw":"400"');

        $this->bcAssertEqualsWithDelta(600.0, $stack->getCharWidth(65), 0.0001);
        $this->assertFalse($stack->isCharDefined(65));
    }

    /**
     * A width may be spelled as a fractional value or as a numeric string, and keeps the
     * value it spells either way.
     *
     * @throws \Throwable
     */
    public function testAWidthKeepsTheValueItSpells(): void
    {
        $this->setupTest();
        $stack = $this->stackWith(
            'spelled',
            '"type":"Core","name":"spelled","dw":600,"cw":{"65":"400","66":300.5,"67":"wide"}',
        );

        $this->bcAssertEqualsWithDelta(400.0, $stack->getCharWidth(65), 0.0001);
        $this->bcAssertEqualsWithDelta(300.5, $stack->getCharWidth(66), 0.0001);
        // a width that is not a number is no width at all, so the default applies
        $this->bcAssertEqualsWithDelta(600.0, $stack->getCharWidth(67), 0.0001);
    }

    /**
     * A glyph bounding box holds four numbers, and is addressed by a character code:
     * anything else is dropped.
     *
     * @throws \Throwable
     */
    public function testAGlyphBoxWithoutFourCornersIsDropped(): void
    {
        $this->setupTest();
        $stack = $this->stackWith(
            'shortglyphbox',
            '"type":"Core","name":"shortglyphbox","dw":600'
            . ',"cbbox":{"65":[1,2],"66":[1,2,3,4],"67":"wide","wide":[1,2,3,4]}',
        );

        $this->assertSame([66 => [1, 2, 3, 4]], $stack->getFont('shortglyphbox')['cbbox']);
        $this->bcAssertEqualsWithDelta([1.0, 2.0, 3.0, 4.0], $stack->getCharBBox(66), 0.0001);
    }

    /**
     * A member read as a string is also accepted as the number a file may spell, as the
     * name of a font made of digits is.
     *
     * @throws \Throwable
     */
    public function testANameSpelledAsANumberIsWrittenAsAName(): void
    {
        $this->setupTest();
        $stack = $this->stackWith('numbername', '"type":"TrueType","name":123,"dw":600');

        $this->assertStringContainsString('/BaseFont /123', $this->fontsBlock($stack));
    }

    /**
     * A descriptor entry the font data declares as a number is read as one, so the
     * artificial bold can multiply the stem width.
     *
     * The bold style is requested and no file declares it, so the artificial styles apply.
     *
     * @throws \Throwable
     */
    public function testADescriptorNumberThatIsNotANumberFallsBackToItsDefault(): void
    {
        $this->setupTest();
        $stack = $this->stackWith(
            'stemless',
            '"type":"TrueType","name":"stemless","dw":600,"desc":{"StemV":"wide"}',
            'B',
        );

        $block = $this->fontsBlock($stack);
        // the entry is dropped, so the artificial bold finds no stem width and states the
        // one it assumes for a bold font
        $this->assertStringContainsString('/StemV 123', $block);
        $this->assertStringContainsString('/BaseFont /stemlessBold', $block);
    }

    /**
     * A descriptor entry the font data does not declare is written out as the file spells
     * it, so it is kept as a number or as the raw syntax of a string.
     *
     * @throws \Throwable
     */
    public function testAnUndeclaredDescriptorEntryIsWrittenAsItIsRead(): void
    {
        $this->setupTest();
        $stack = $this->stackWith(
            'extradesc',
            '"type":"TrueType","name":"extradesc","dw":600'
            . ',"desc":{"FontWeight":700,"FontStretch":"/Normal","Panose":[1]}',
        );

        $block = $this->fontsBlock($stack);
        $this->assertStringContainsString('/FontWeight 700', $block);
        $this->assertStringContainsString('/FontStretch /Normal', $block);
        // an array has no form in the dictionary
        $this->assertStringNotContainsString('/Panose', $block);
    }

    /**
     * A member the font data does not declare is not carried into it: nothing reads it,
     * and it would make the buffer something other than the font data it is read as.
     *
     * @throws \Throwable
     */
    public function testAMemberTheFontDataDoesNotDeclareIsDropped(): void
    {
        $this->setupTest();
        $stack = $this->stackWith('extramember', '"type":"Core","name":"extramember","dw":600,"unlisted":{"a":1}');

        $this->assertArrayNotHasKey('unlisted', $stack->getFont('extramember'));
    }

    /**
     * The umbrella case: a file whose members are all of the wrong shape is read at the
     * declared types, so the font loads on its defaults and is written out.
     *
     * @throws \Throwable
     */
    public function testAFileOfWrongShapesIsReadOnItsDefaults(): void
    {
        $this->setupTest();
        $stack = $this->stackWith(
            'wrongshapes',
            '"type":"TrueTypeUnicode","name":"wrongshapes","dw":[1,2],"up":"x","ut":true,"diff":[],'
            . '"file":{"a":1},"ctg":12,"cw":true,"cwu":"x","cbbox":"x","cbboxu":{"65":"x"},'
            . '"isUnicode":"yes","desc":"x","cidinfo":[],"subsetchars":"x","enc":null',
        );

        $font = $stack->getFont('wrongshapes');
        $this->assertSame(600, $font['dw']); // the default width of a font that declares none
        $this->assertSame('', $font['file']);
        // a number is spelled out where a string is read, so the file names a program
        $this->assertSame('12', $font['ctg']);
        $this->assertSame([], $font['cw']);
        $this->assertStringContainsString('/BaseFont /wrongshapes', $this->fontsBlock($stack));
    }

    /**
     * A character code is the key of several maps of the font data, and is read as one
     * however the file spells it.
     *
     * @throws \Throwable
     */
    public function testACharacterCodeIsReadWhateverItsSpelling(): void
    {
        $this->setupTest();
        $stack = $this->stackWith(
            'spelledcodes',
            '"type":"Core","name":"spelledcodes","dw":600,"cw":{"065":400,"wide":500}',
        );

        $this->bcAssertEqualsWithDelta(400.0, $stack->getCharWidth(65), 0.0001);
        // a key that is not a character code addresses no character
        $this->assertSame([65 => 400], $stack->getFont('spelledcodes')['cw']);
    }

    /**
     * The maps the font data fills while a document is written may also be stated by the
     * file, and are read at their own types.
     *
     * @throws \Throwable
     */
    public function testTheRemainingMapsAreReadAtTheirDeclaredTypes(): void
    {
        $this->setupTest();
        $stack = $this->stackWith(
            'statedmaps',
            '"type":"Core","name":"statedmaps","dw":600,"FontBBox":[1,"2","wide"]'
            . ',"subsetchars":{"65":0,"66":1,"wide":1},"enc_map":{"65":"A","66":7,"wide":"B"}',
        );

        $font = $stack->getFont('statedmaps');
        $this->assertSame([1, 2], $font['FontBBox']);
        $this->assertFalse($font['subsetchars'][65] ?? null);
        $this->assertTrue($font['subsetchars'][66] ?? null);
        $this->assertSame(['A', '7'], [$font['enc_map'][65] ?? null, $font['enc_map'][66] ?? null]);
        $this->assertArrayNotHasKey('wide', $font['enc_map']);
    }

    /**
     * A descriptor key holding nothing names nothing, so the entry is dropped before it
     * reaches the dictionary.
     *
     * @throws \Throwable
     */
    public function testAnEmptyDescriptorKeyIsDropped(): void
    {
        $this->setupTest();
        $stack = $this->stackWith('namelesskey', '"type":"TrueType","name":"namelesskey","dw":600,"desc":{"":5}');

        $this->assertStringNotContainsString('/ 5', $this->fontsBlock($stack));
    }

    /**
     * The type is the one member that has to be there and to be a string, as every branch
     * of the loading reads it.
     *
     * @throws \Throwable
     */
    public function testATypeThatIsNotAStringIsReported(): void
    {
        $this->setupTest();
        \file_put_contents($this->getFontPath() . 'listtype.json', '{"type":["Core"],"name":"listtype"}');

        $stack = new Stack(1);
        $objnum = 1;
        $this->assertThrowsMessage(
            FontException::class,
            'bad format',
            /** @throws \Throwable */
            static function () use ($stack, &$objnum): void {
                $stack->insert($objnum, 'listtype');
            },
        );
    }
}
