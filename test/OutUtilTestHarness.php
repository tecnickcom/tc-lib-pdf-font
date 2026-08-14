<?php

/**
 * OutUtilTestHarness.php
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
 * Test helper exposing the protected width helpers of OutUtil.
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
class OutUtilTestHarness extends \Com\Tecnick\Pdf\Font\OutUtil
{
    /**
     * @param array<int, int> $widths Character widths indexed by CID.
     * @param int             $dwt    Default width.
     */
    public function runBuild(array $widths, int $dwt): string
    {
        return $this->formatWidthRanges($this->buildWidthRanges($widths, $dwt));
    }

    /**
     * @param TFontData $font
     */
    public function runCharWidths(array $font, int $cidoffset = 0): string
    {
        return $this->getCharWidths($font, $cidoffset);
    }

    /**
     * @param TFontData $font
     */
    public function runGidWidths(array $font): string
    {
        return $this->getGidWidths($font);
    }
}
