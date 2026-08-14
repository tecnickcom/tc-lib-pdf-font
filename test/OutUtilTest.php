<?php

/**
 * OutUtilTest.php
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
 * Tests for the /W array builder shared by the font output classes.
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
class OutUtilTest extends TestUtil
{
    /**
     * Build the slice of the font data that the width helpers read.
     *
     * @param array<int, int>  $cwd     Widths indexed by CID or codepoint.
     * @param array<int, int>  $usedgid Glyph index => codepoint it was encoded from.
     * @param array<int, bool> $chars   Subset characters.
     *
     * @return TFontData
     */
    private function fontWith(array $cwd, int $dwt, bool $subset = false, array $usedgid = [], array $chars = []): array
    {
        /** @var TFontData $font */
        $font = [
            'cw' => $cwd,
            'dw' => $dwt,
            'subset' => $subset,
            'subsetchars' => $chars,
            'usedgid' => $usedgid,
        ];

        return $font;
    }

    public function testWidthsEqualToTheDefaultAreOmitted(): void
    {
        $util = new OutUtilTestHarness();
        // every width equals dw, so the /W array carries nothing
        $this->assertSame('/W [ ]', $util->runBuild([10 => 500, 11 => 500], 500));
    }

    public function testASingleWidthUsesTheCompactIntervalForm(): void
    {
        $util = new OutUtilTestHarness();
        $this->assertSame('/W [ 10 10 300 ]', $util->runBuild([10 => 300], 500));
    }

    public function testConsecutiveEqualWidthsCollapseIntoOneInterval(): void
    {
        $util = new OutUtilTestHarness();
        // 10..12 all 300 → "first last width"
        $this->assertSame('/W [ 10 12 300 ]', $util->runBuild([10 => 300, 11 => 300, 12 => 300], 500));
    }

    public function testNonConsecutiveCidsStartSeparateRanges(): void
    {
        $util = new OutUtilTestHarness();
        $this->assertSame('/W [ 10 10 300 20 20 400 ]', $util->runBuild([10 => 300, 20 => 400], 500));
    }

    /**
     * A run of differing widths is emitted as an explicit list rather than an interval.
     */
    public function testMixedConsecutiveWidthsUseTheListForm(): void
    {
        $util = new OutUtilTestHarness();
        $this->assertSame('/W [ 10 [ 300 400 500 ] ]', $util->runBuild([10 => 300, 11 => 400, 12 => 500], 600));
    }

    /**
     * A repeat that starts in the middle of a list has to close the list, rewind the range
     * id to the first CID of the repeat, and reopen it as an interval.
     */
    public function testARepeatStartingMidListSplitsTheRange(): void
    {
        $util = new OutUtilTestHarness();
        // 10:100, 11:200, 12:200 → the pair 11..12 is a repeat inside a list
        $this->assertSame('/W [ 10 [ 100 200 200 ] ]', $util->runBuild([10 => 100, 11 => 200, 12 => 200], 999));
    }

    /**
     * A repeat long enough to be worth an interval is not merged back into the list.
     */
    public function testALongRepeatIsKeptAsItsOwnInterval(): void
    {
        $util = new OutUtilTestHarness();
        $widths = [10 => 100, 11 => 200, 12 => 200, 13 => 200, 14 => 200];
        // the leftover single width at 10 falls back to the compact interval form
        $this->assertSame('/W [ 10 10 100 11 14 200 ]', $util->runBuild($widths, 999));
    }

    public function testAnIntervalFollowedByADifferentWidthOpensANewRange(): void
    {
        $util = new OutUtilTestHarness();
        // 10..12 repeat 300, then 13 differs → 13 starts a fresh range
        $widths = [10 => 300, 11 => 300, 12 => 300, 13 => 400];
        $this->assertSame('/W [ 10 12 300 13 13 400 ]', $util->runBuild($widths, 999));
    }

    public function testDefaultWidthBreaksTheConsecutiveRun(): void
    {
        $util = new OutUtilTestHarness();
        // CID 11 equals dw and is skipped, so 12 is no longer consecutive with 10
        $widths = [10 => 300, 11 => 500, 12 => 400];
        $this->assertSame('/W [ 10 10 300 12 12 400 ]', $util->runBuild($widths, 500));
    }

    // -------------------------------------------------------------------------
    // getCharWidths
    // -------------------------------------------------------------------------

    public function testGetCharWidthsSortsByCidAndAppliesTheOffset(): void
    {
        $util = new OutUtilTestHarness();
        // insertion order is deliberately shuffled: ksort must put it back
        $font = $this->fontWith([42 => 400, 41 => 300], 999);
        $this->assertSame('/W [ 10 [ 300 400 ] ]', $util->runCharWidths($font, 31));
    }

    public function testGetCharWidthsDropsCodesBelowTheCidOffset(): void
    {
        $util = new OutUtilTestHarness();
        // code 30 lands on CID -1, which no content stream can address, so it is dropped
        $font = $this->fontWith([30 => 700, 32 => 300, 33 => 400], 999);

        $this->assertSame('/W [ 1 [ 300 400 ] ]', $util->runCharWidths($font, 31));
    }

    public function testGetCharWidthsSkipsUnusedCharsWhenSubsetting(): void
    {
        $util = new OutUtilTestHarness();
        $font = $this->fontWith([10 => 300, 11 => 400, 12 => 500], 999, true, [], [10 => true, 12 => true]);

        // CID 11 is not in the subset, so it is dropped and 12 starts a new range
        $this->assertSame('/W [ 10 10 300 12 12 500 ]', $util->runCharWidths($font));
    }

    public function testGetCharWidthsKeepsEveryCharWhenNotSubsetting(): void
    {
        $util = new OutUtilTestHarness();
        // subsetchars is ignored entirely while subset is false
        $font = $this->fontWith([10 => 300, 11 => 400], 999, false, [], [10 => true]);

        $this->assertSame('/W [ 10 [ 300 400 ] ]', $util->runCharWidths($font));
    }

    // -------------------------------------------------------------------------
    // getGidWidths
    // -------------------------------------------------------------------------

    public function testGetGidWidthsListsOnlyTheGlyphsUsedByTheDocument(): void
    {
        $util = new OutUtilTestHarness();
        // usedgid maps glyph index => codepoint it was encoded from
        $font = $this->fontWith([65 => 300, 66 => 400, 67 => 500], 999, false, [36 => 65, 37 => 66]);

        $this->assertSame('/W [ 36 [ 300 400 ] ]', $util->runGidWidths($font));
    }

    public function testGetGidWidthsFallsBackToTheDefaultWidthForUnknownCodepoints(): void
    {
        $util = new OutUtilTestHarness();
        // codepoint 999 has no entry in cw, so the glyph takes dw and is therefore omitted
        $font = $this->fontWith([65 => 300], 250, false, [36 => 65, 37 => 999]);

        $this->assertSame('/W [ 36 36 300 ]', $util->runGidWidths($font));
    }

    public function testGetGidWidthsSortsByGlyphIndex(): void
    {
        $util = new OutUtilTestHarness();
        $font = $this->fontWith([65 => 300, 66 => 400], 999, false, [37 => 66, 36 => 65]);

        $this->assertSame('/W [ 36 [ 300 400 ] ]', $util->runGidWidths($font));
    }

    public function testGetGidWidthsReturnsAnEmptyArrayWhenNoGlyphWasUsed(): void
    {
        $util = new OutUtilTestHarness();
        $this->assertSame('/W [ ]', $util->runGidWidths($this->fontWith([65 => 300], 999)));
    }
}
