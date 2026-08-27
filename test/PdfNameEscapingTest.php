<?php

/**
 * PdfNameEscapingTest.php
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
use Com\Tecnick\Pdf\Font\Load;
use Com\Tecnick\Pdf\Font\Output;

/**
 * The font and encoding names are emitted as PDF name objects.
 *
 * The 'name' and 'enc' members of a definition file may carry characters that ISO 32000-1
 * 7.3.5 requires to be written as a '#XX' escape, and every name reaches the output through
 * the encoder of the encrypt object.
 *
 * @since     2026-08-27
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @phpstan-import-type TFontData from \Com\Tecnick\Pdf\Font\Load
 */
class PdfNameEscapingTest extends TestUtil
{
    /**
     * Build the data of a font that has no program on disk.
     *
     * @param array<string, mixed> $members Members overriding the defaults.
     *
     * @return TFontData
     */
    private function fontWith(array $members): array
    {
        /** @var TFontData $font */
        $font = \array_replace(Load::DEFAULT_DATA, ['dw' => 600, 'i' => 1, 'n' => 1], $members);

        return $font;
    }

    /**
     * Returns the PDF fonts block of a single font.
     *
     * @param TFontData $font Font to emit.
     *
     * @throws EncException
     * @throws FileException
     * @throws FontException
     */
    private function fontsBlock(array $font): string
    {
        return (new Output(['test' => $font], 1, new Encrypt()))->getFontsBlock();
    }

    /**
     * @throws EncException
     * @throws FileException
     * @throws FontException
     */
    public function testACoreFontNameIsEscaped(): void
    {
        $block = $this->fontsBlock($this->fontWith([
            'type' => 'Core',
            'family' => 'helvetica',
            'name' => 'Fun Font#Bold',
        ]));

        $this->assertStringContainsString('/BaseFont /Fun#20Font#23Bold', $block);
    }

    /**
     * A simple font names itself twice, in the font dictionary and in its descriptor, and
     * a reader matching the two would not find them equal if only one were escaped.
     *
     * @throws EncException
     * @throws FileException
     * @throws FontException
     */
    public function testASimpleFontNameIsEscapedInBothTheFontAndItsDescriptor(): void
    {
        $block = $this->fontsBlock($this->fontWith([
            'type' => 'TrueType',
            'name' => 'Fun Font#Bold',
            'enc' => 'cp1252',
            'cw' => [32 => 250],
        ]));

        $this->assertStringContainsString('/BaseFont /Fun#20Font#23Bold', $block);
        $this->assertStringContainsString('/FontName /Fun#20Font#23Bold', $block);
        $this->assertStringNotContainsString('Fun Font#Bold', $block);
    }

    /**
     * The CID-0 font dictionary appends the encoding to the name, so both parts are
     * escaped and the '-' joining them, which needs none, is left alone.
     *
     * @throws EncException
     * @throws FileException
     * @throws FontException
     */
    public function testACid0FontNameAndEncodingAreEscaped(): void
    {
        $block = $this->fontsBlock($this->fontWith([
            'type' => 'cidfont0',
            'name' => 'Fun Font',
            'enc' => 'Uni JIS-UCS2-H',
            'cw' => [1 => 250],
        ]));

        $this->assertStringContainsString('/BaseFont /Fun#20Font-Uni#20JIS-UCS2-H', $block);
        $this->assertStringContainsString('/Encoding /Uni#20JIS-UCS2-H', $block);
        $this->assertStringContainsString('/FontName /Fun#20Font', $block);
    }

    /**
     * The six-letter tag and the '+' joining it to the name are the form ISO 32000-1 9.6.4
     * prescribes for a reduced program, so the separator stays literal.
     *
     * @throws EncException
     * @throws FontException
     */
    public function testTheSubsetTagKeepsItsPlusSeparator(): void
    {
        $harness = new OutputTestOutFont();
        $out = $harness->runGetTrueTypeUnicode($this->fontWith([
            'type' => 'TrueTypeUnicode',
            'name' => 'Fun Font',
            'enc' => 'Identity-H',
            'subset' => true,
        ]));

        $this->assertStringContainsString('/BaseFont /AAAAAB+Fun#20Font', $out);
    }

    /**
     * The keys of the font descriptor come from the definition file and are names too, so
     * one carrying a character the form does not accept is escaped.
     *
     * @throws EncException
     * @throws FileException
     * @throws FontException
     */
    public function testADescriptorKeyIsEscaped(): void
    {
        $block = $this->fontsBlock($this->fontWith([
            'type' => 'TrueType',
            'name' => 'FreeSans',
            'enc' => 'cp1252',
            'desc' => [
                'Fun Key' => 12,
                'StemV' => 80,
            ],
        ]));

        $this->assertStringContainsString('/Fun#20Key 12', $block);
        $this->assertStringContainsString('/StemV 80', $block);
    }

    /**
     * A key holding nothing names nothing, so the entry is dropped: writing it would give
     * '/ 12', which reads as a name of no characters followed by a stray number.
     *
     * @throws EncException
     * @throws FileException
     * @throws FontException
     */
    public function testAnEmptyDescriptorKeyIsDropped(): void
    {
        $block = $this->fontsBlock($this->fontWith([
            'type' => 'TrueType',
            'name' => 'FreeSans',
            'enc' => 'cp1252',
            'desc' => [
                '' => 12,
                'StemV' => 80,
            ],
        ]));

        $this->assertStringNotContainsString('/ 12', $block);
        $this->assertStringContainsString('/StemV 80', $block);
    }

    /**
     * A name made only of the characters a PDF name object accepts is written as it is.
     *
     * @throws EncException
     * @throws FontException
     */
    public function testANameNeedingNoEscapeIsUnchanged(): void
    {
        $harness = new OutputTestOutFont();
        $out = $harness->runGetTrueTypeUnicode($this->fontWith([
            'type' => 'TrueTypeUnicode',
            'name' => 'FreeSans',
            'enc' => 'Identity-H',
        ]));

        $this->assertStringContainsString('/BaseFont /FreeSans', $out);
        $this->assertStringContainsString('/Encoding /Identity-H', $out);
    }
}
