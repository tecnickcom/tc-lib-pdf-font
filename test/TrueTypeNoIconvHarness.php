<?php

/**
 * TrueTypeNoIconvHarness.php
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
 * Test helper simulating a build without the iconv extension, which is only a suggested
 * dependency of this library.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TrueTypeNoIconvHarness extends \Com\Tecnick\Pdf\Font\Import\TrueType
{
    #[\Override]
    protected function hasIconv(): bool
    {
        return false;
    }

    public function runConvertStringEncoding(string $str, int $platformId, int $encodingId): string
    {
        return $this->convertStringEncoding($str, $platformId, $encodingId);
    }
}
