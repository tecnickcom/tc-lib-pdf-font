<?php

/**
 * GlyphIndexRangeTest.php
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

use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * A glyph index is a 16-bit value, and everything that records one keeps it in that range.
 *
 * An index above 0xFFFF read from the 'ctgu' member of a definition file is reported as
 * .notdef.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class GlyphIndexRangeTest extends TestUtil
{
    /**
     * Write a TrueTypeUnicode definition whose 'ctgu' member holds the given entries.
     *
     * @param array<int, int> $ctgu Supplementary-plane codepoint => glyph index.
     */
    private function writeDefinition(string $key, array $ctgu): void
    {
        $pairs = [];
        foreach ($ctgu as $codepoint => $gid) {
            $pairs[] = '"' . $codepoint . '":' . $gid;
        }

        \file_put_contents(
            $this->getFontPath() . $key . '.json',
            '{"type":"TrueTypeUnicode","name":"'
            . $key
            . '","dw":600'
            . ',"desc":{"FontBBox":"[0 -200 1000 800]","Ascent":800,"Descent":-200}'
            . ',"cw":{"65":400},"ctg":"'
            . $key
            . '.ctg","ctgu":{'
            . \implode(',', $pairs)
            . '}}',
        );

        // an all-notdef CIDToGIDMap: the codepoints under test are above its range anyway
        \file_put_contents($this->getFontPath() . $key . '.ctg', \str_repeat("\x00", 131_072));
    }

    /**
     * Returns the glyph indexes the stack recorded for the given font.
     *
     * @return array<int, int>
     *
     * @throws FontException
     */
    private function usedGidOf(Stack $stack, string $key): array
    {
        return $stack->getFont($key)['usedgid'];
    }

    /**
     * @throws FontException
     */
    private function stackFor(string $key): Stack
    {
        $objnum = 1;
        $stack = new Stack(1);
        $stack->insert($objnum, $key, '', 10, 0, 1);

        return $stack;
    }

    /**
     * @throws FontException
     */
    public function testAnInRangeCtguEntryIsReported(): void
    {
        $this->setupTest();
        $this->writeDefinition('ctgurange', [0x1_D400 => 0xFFFF]);

        $this->assertSame(0xFFFF, $this->stackFor('ctgurange')->getGidForOrd(0x1_D400));
    }

    /**
     * @throws FontException
     */
    public function testACtguEntryAboveTheGlyphIndexRangeReportsNotdef(): void
    {
        $this->setupTest();
        $this->writeDefinition('ctguhigh', [0x1_D400 => 0x1_2345]);

        $stack = $this->stackFor('ctguhigh');
        $this->assertSame(0, $stack->getGidForOrd(0x1_D400));
        // nothing is recorded for a glyph that does not exist
        $this->assertSame("\x00\x00", $stack->ordArrToGidStr([0x1_D400]));
        $this->assertSame([], $this->usedGidOf($stack, 'ctguhigh'));
    }

    /**
     * @throws FontException
     */
    public function testANegativeCtguEntryReportsNotdef(): void
    {
        $this->setupTest();
        $this->writeDefinition('ctgulow', [0x1_D400 => -1]);

        $this->assertSame(0, $this->stackFor('ctgulow')->getGidForOrd(0x1_D400));
    }

    /**
     * @throws FontException
     */
    public function testRecordingAGlyphIndexAboveTheRangeIsRefused(): void
    {
        $this->setupTest();
        $this->writeDefinition('gidrange', []);
        $stack = $this->stackFor('gidrange');

        $this->assertThrowsMessage(
            FontException::class,
            'outside the 0..65535 range',
            /** @throws \Throwable */
            static fn() => $stack->addUsedGid('gidrange', 0x1_2345, 65),
        );
    }

    /**
     * @throws FontException
     */
    public function testRecordingANegativeGlyphIndexIsRefused(): void
    {
        $this->setupTest();
        $this->writeDefinition('gidneg', []);
        $stack = $this->stackFor('gidneg');

        $this->assertThrowsMessage(
            FontException::class,
            'outside the 0..65535 range',
            /** @throws \Throwable */
            static fn() => $stack->addUsedGid('gidneg', -1, 65),
        );
    }

    /**
     * The two ends of the range are valid glyph indexes and are recorded.
     *
     * @throws FontException
     */
    public function testTheBoundsOfTheRangeAreRecorded(): void
    {
        $this->setupTest();
        $this->writeDefinition('gidbounds', []);
        $stack = $this->stackFor('gidbounds');

        $stack->addUsedGid('gidbounds', 0, 65);
        $stack->addUsedGid('gidbounds', 0xFFFF, 66);

        $this->assertSame([0 => 65, 0xFFFF => 66], $this->usedGidOf($stack, 'gidbounds'));
    }
}
