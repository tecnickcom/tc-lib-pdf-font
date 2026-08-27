<?php

/**
 * SubsetCharRecordingTest.php
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
use Com\Tecnick\File\File as ObjFile;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\FontPaths;
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Stack;
use Com\Tecnick\Pdf\Font\Subset;
use Com\Tecnick\Pdf\Font\Zlib;

/**
 * Encoding a string as glyph indices feeds the subset.
 *
 * Stack::ordArrToGidStr() records the glyph and the codepoint it was encoded from. The
 * subset is seeded with the codes 0..255, so only a character above 255 depends on the
 * codepoint being recorded.
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
class SubsetCharRecordingTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/';

    /**
     * A codepoint above the seeded 0..255 range, carried by FreeSerif.
     */
    private const EURO = 0x20AC;

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    private function subsetStack(): Stack
    {
        $this->setupTest();
        new Import(\dirname(__DIR__) . self::MIRROR . 'freefont/FreeSerif.ttf');

        $objnum = 1;
        $stack = new Stack(1, true);
        $stack->insert($objnum, 'freeserif', '', 10, 0, 1, '', true);

        return $stack;
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testEncodingRecordsBothTheGlyphAndTheSubsetCharacter(): void
    {
        $stack = $this->subsetStack();
        $stack->ordArrToGidStr([self::EURO]);

        $font = $stack->getFont($stack->getCurrentFontKey());
        $gid = $stack->getGidForOrd(self::EURO);

        $this->assertGreaterThan(0, $gid, 'FreeSerif carries the euro sign');
        $this->assertSame(self::EURO, $font['usedgid'][$gid] ?? null, 'the glyph is recorded');
        $this->assertTrue($font['subsetchars'][self::EURO] ?? false, 'the codepoint enters the subset');
    }

    /**
     * The outline of the character survives in the emitted program, which is what the
     * recorded glyph index is pointed at.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTheEncodedGlyphKeepsItsOutlineInTheSubset(): void
    {
        $stack = $this->subsetStack();
        $stack->ordArrToGidStr([self::EURO]);

        $reader = new SfntReader($this->buildSubset($stack));
        $this->assertGreaterThan(
            0,
            $reader->getGlyphLength($stack->getGidForOrd(self::EURO)),
            'the euro sign has an outline',
        );

        // a glyph the document never used is emptied, so the check above is not vacuous
        $unused = $stack->getGidForOrd(0x2211); // n-ary summation
        $this->assertGreaterThan(0, $unused);
        $this->assertSame(0, $reader->getGlyphLength($unused));
    }

    /**
     * A codepoint without a glyph is not recorded on either list.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAMissingGlyphEntersNeitherList(): void
    {
        $stack = $this->subsetStack();
        $stack->ordArrToGidStr([0xE000]); // private use, no glyph

        $font = $stack->getFont($stack->getCurrentFontKey());
        $this->assertSame([], $font['usedgid']);
        $this->assertArrayNotHasKey(0xE000, $font['subsetchars']);
    }

    /**
     * Builds the subset the output would embed for the current font of the stack.
     *
     * @throws FileException
     * @throws FontException
     */
    private function buildSubset(Stack $stack): string
    {
        $font = $stack->getFont($stack->getCurrentFontKey());

        $fileHelper = new ObjFile(allowedPaths: FontPaths::buildAllowedPaths());
        $path = FontPaths::findFontFile($font['dir'], $font['file']);
        $this->assertNotSame('', $path, 'the font program is stored next to its definition');

        $program = $fileHelper->getLocalFileData($path);
        $this->assertNotFalse($program);

        $uncompressed = Zlib::uncompress($program);
        $this->assertNotFalse($uncompressed);

        return (new Subset($uncompressed, $font, $fileHelper, \array_filter($font['subsetchars'])))->getSubsetFont();
    }
}
