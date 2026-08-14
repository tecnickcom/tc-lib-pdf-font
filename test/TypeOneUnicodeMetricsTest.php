<?php

/**
 * TypeOneUnicodeMetricsTest.php
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

use Com\Tecnick\File\Exception as FileException;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\FontType;
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * A Type1 font is measurable by codepoint, and .notdef claims no character code.
 *
 * The importer fills the map keyed by codepoint as well as the one keyed by encoding byte:
 * Stack::getCharWidth() consults the codepoint-keyed one first, and the two differ over the
 * whole 0x80-0x9F block of WinAnsiEncoding, which is how a Type1 font is emitted. The
 * '.notdef' glyph name resolves to no character code, although every encoding map lists
 * that name at index 0.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TypeOneUnicodeMetricsTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/pdfa/pfb/';

    /**
     * WinAnsi byte and Unicode codepoint of the glyphs the two maps must agree on.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const GLYPHS = [
        'quoteright' => [146, 0x2019],
        'quotedblleft' => [147, 0x201C],
        'Euro' => [128, 0x20AC],
        'A' => [65, 0x0041],
    ];

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    private function helveticaStack(): Stack
    {
        $this->setupTest();
        new Import(\dirname(__DIR__) . self::MIRROR . 'PDFAHelvetica.pfb', '', FontType::Type1, 'cp1252');

        $objnum = 1;
        $stack = new Stack(1);
        $stack->insert($objnum, 'pdfahelvetica', '', 10, 0, 1);

        return $stack;
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testEveryGlyphIsAlsoRecordedUnderItsCodepoint(): void
    {
        $stack = $this->helveticaStack();
        $font = $stack->getFont($stack->getCurrentFontKey());

        $this->assertNotSame([], $font['cwu'], 'the codepoint keyed map is built');

        foreach (self::GLYPHS as $name => [$byte, $codepoint]) {
            $this->assertSame($font['cw'][$byte] ?? null, $font['cwu'][$codepoint] ?? null, $name . ' by codepoint');
        }
    }

    /**
     * The whole point of the map: measuring by codepoint reports the real advance.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testMeasuringByCodepointReportsTheGlyphAdvance(): void
    {
        $stack = $this->helveticaStack();
        $metric = $stack->getCurrentFont();

        foreach (self::GLYPHS as $name => [$byte, $codepoint]) {
            $this->bcAssertEqualsWithDelta(
                $metric['cw'][$byte] ?? null,
                $stack->getCharWidth($codepoint),
                0.0001,
                $name,
            );
        }

        // Without the map the codepoint falls back to the default width, so the assertions
        // above only mean something for a glyph that is not that wide. The euro sign sits
        // at 128, in the block where the WinAnsi byte and the codepoint differ.
        $this->assertNotEquals($metric['dw'], $metric['cw'][128] ?? null);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTheNotdefGlyphClaimsNoCharacterCode(): void
    {
        $stack = $this->helveticaStack();
        $font = $stack->getFont($stack->getCurrentFontKey());

        $this->assertArrayNotHasKey(0, $font['cw'], 'the .notdef width is not the width of code 0');
        $this->assertFalse($stack->isCharDefined(0));
        // the encoded glyphs are untouched
        $this->assertTrue($stack->isCharDefined(65));
    }
}
