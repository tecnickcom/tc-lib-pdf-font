<?php

/**
 * StackTestHarness.php
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

/**
 * Test helper exposing the protected metric helpers of Stack.
 *
 * @since     2026-08-27
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @phpstan-import-type TBBox from \Com\Tecnick\Pdf\Font\Stack
 */
class StackTestHarness extends \Com\Tecnick\Pdf\Font\Stack
{
    /**
     * @param array<int, array<int, int>> $map    Bounding boxes indexed by character code.
     * @param float                       $wratio Horizontal ratio.
     * @param float                       $cratio Vertical ratio.
     *
     * @return array<int, TBBox>
     */
    public function runScaleBBoxMap(array $map, float $wratio, float $cratio): array
    {
        return $this->scaleBBoxMap($map, $wratio, $cratio);
    }
}
