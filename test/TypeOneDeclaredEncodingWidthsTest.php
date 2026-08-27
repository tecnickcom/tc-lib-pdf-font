<?php

/**
 * TypeOneDeclaredEncodingWidthsTest.php
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

use Com\Tecnick\Pdf\Font\Import\TypeOne;
use Com\Tecnick\Pdf\Font\Load;
use Com\Tecnick\Unicode\Data\Encoding;

/**
 * The /Widths array of an emitted Type 1 font is indexed by the encoding the font
 * dictionary declares, which overrides the built-in encoding array of the program.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TypeOneDeclaredEncodingWidthsTest extends TestUtil
{
    /**
     * Build a Type 1 importer whose font data declares the given encoding.
     */
    private function buildTypeOne(string $enc): TypeOne
    {
        $class = new \ReflectionClass(TypeOne::class);
        $instance = $class->newInstanceWithoutConstructor();
        $fdt = Load::DEFAULT_DATA;
        $fdt['enc'] = $enc;
        $fdt['enc_map'] = $enc === '' ? [] : Encoding::MAP[$enc] ?? [];
        (new \ReflectionProperty(TypeOne::class, 'fdt'))->setValue($instance, $fdt);
        (new \ReflectionProperty(TypeOne::class, 'font'))->setValue($instance, '');

        return $instance;
    }

    /**
     * @param array<string, int> $imap Built-in encoding array of the program.
     *
     * @return array<int, int>
     *
     * @throws \ReflectionException
     */
    private function cidsOf(string $enc, array $imap, string $name): array
    {
        $method = new \ReflectionMethod(TypeOne::class, 'getCids');
        /** @var mixed $cids */
        $cids = $method->invoke($this->buildTypeOne($enc), $imap, [0 => '', 1 => $name, 2 => '']);
        $this->assertIsArray($cids);

        /** @var array<int, int> $cids */
        return $cids;
    }

    /**
     * An original Adobe Type 1 places 'quoteright' at 39 through its own array, while
     * WinAnsi names 39 'quotesingle' and puts 'quoteright' at 146: the declared encoding
     * decides.
     *
     * @throws \Throwable
     */
    public function testTheDeclaredEncodingOverridesTheBuiltInArray(): void
    {
        $this->assertSame([146], $this->cidsOf('cp1252', ['quoteright' => 39], 'quoteright'));
    }

    /**
     * A font emitted without an /Encoding entry is addressed through its own array, which
     * is then the only source of a character code.
     *
     * @throws \Throwable
     */
    public function testTheBuiltInArrayDecidesWhenNoEncodingIsDeclared(): void
    {
        $this->assertSame([39], $this->cidsOf('', ['quoteright' => 39], 'quoteright'));
    }

    /**
     * The declared encoding may not name a glyph the font carries under one of its other
     * Adobe Glyph List names ('micro' for 'mu'), whose code the built-in array reports.
     *
     * @throws \Throwable
     */
    public function testTheBuiltInArrayAnswersForAGlyphTheEncodingDoesNotName(): void
    {
        $this->assertSame([181], $this->cidsOf('cp1252', ['micro' => 181], 'micro'));
    }

    /**
     * A glyph named by neither has no character code.
     *
     * @throws \Throwable
     */
    public function testAGlyphNamedByNeitherHasNoCode(): void
    {
        $this->assertSame([], $this->cidsOf('cp1252', ['micro' => 181], 'notinanyencoding'));
    }

    /**
     * An encoding may give one name several codes, and every one of them is reported.
     *
     * @throws \Throwable
     */
    public function testEveryCodeOfARepeatedGlyphNameIsReported(): void
    {
        // WinAnsi assigns 'hyphen' both 45 and 173 (ISO 32000-1 Annex D.2)
        $this->assertSame([45, 173], $this->cidsOf('cp1252', ['hyphen' => 45], 'hyphen'));
    }

    /**
     * '.notdef' is the glyph a code falls back to, not a character.
     *
     * @throws \Throwable
     */
    public function testNotdefHasNoCode(): void
    {
        $this->assertSame([], $this->cidsOf('cp1252', ['.notdef' => 0], '.notdef'));
    }
}
