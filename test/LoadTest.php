<?php

/**
 * LoadTest.php
 *
 * @since     2026-05-21
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
 * Load Test
 *
 * @since     2026-05-21
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class LoadTest extends TestUtil
{
    /** @throws \Com\Tecnick\Pdf\Font\Exception */
    public function testLoadAppliesFallbackStylesForMissingVariantFiles(): void
    {
        $load = new LoadTestHarness('customfont', '');
        $load->setModeAndMetrics(true, true, 0, 0, 0);

        $load->load();

        $this->assertSame('customfontBoldItalic', $load->getNameValue());
        $this->assertSame(123, $load->getStemVValue());
        $this->assertSame(-11, $load->getItalicAngleValue());
        $this->assertSame(64, $load->getFlagsValue());
    }

    /**
     * A definition stating a width below zero states no usable width: a negative default
     * would move the text backwards wherever a glyph has no width of its own.
     *
     * @throws \Com\Tecnick\Pdf\Font\Exception
     */
    public function testLoadReplacesADefaultWidthBelowZero(): void
    {
        $load = new LoadTestHarness('customfont', 'Custom');
        $load->setModeAndMetrics(false, false, 70, 0, 32);
        $load->setDefaultWidthValue(-100);

        $load->load();

        // the width of the space is the first fallback, ahead of the fixed 600
        $this->assertSame(500, $load->getDefaultWidthValue());
    }

    /**
     * The width of the space is the first fallback of the default width, so it states no
     * usable width when it is below zero either.
     *
     * @throws \Com\Tecnick\Pdf\Font\Exception
     */
    public function testLoadIgnoresASpaceWidthBelowZero(): void
    {
        $load = new LoadTestHarness('customfont', 'Custom');
        $load->setModeAndMetrics(false, false, 70, 0, 32);
        $load->setDefaultWidthValue(-100);
        $load->setSpaceWidthValue(-250);

        $load->load();

        // both the declared default and the space state no width, so the fixed one applies
        $this->assertSame(600, $load->getDefaultWidthValue());
    }

    public function testFindFontDirectoriesExcludesEmptyRootEntry(): void
    {
        $load = new LoadTestHarness('customfont', '');

        $dirs = $load->exposeFontDirectories();

        // An empty entry makes findFontFile() probe the filesystem root
        // (is_readable('' . DIRECTORY_SEPARATOR . $file) === is_readable('/<font>.json')),
        // which fails and emits a warning under open_basedir restrictions.
        // See https://github.com/tecnickcom/tc-lib-pdf/issues/238
        $this->assertNotContains('', $dirs);
    }

    /** @throws \Com\Tecnick\Pdf\Font\Exception */
    public function testLoadUpdatesExistingBoldAndItalicMetrics(): void
    {
        $load = new LoadTestHarness('customfont', 'Custom');
        $load->setModeAndMetrics(true, true, 100, -20, 1);

        $load->load();

        $this->assertSame('CustomBoldItalic', $load->getNameValue());
        $this->assertSame(175, $load->getStemVValue());
        $this->assertSame(-31, $load->getItalicAngleValue());
        $this->assertSame(65, $load->getFlagsValue());
    }
}
