<?php

/**
 * OutputTestOutFont.php
 *
 * @since     2011-05-23
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

use Com\Tecnick\Pdf\Encrypt\Encrypt;
use Com\Tecnick\Pdf\Encrypt\Exception as EncException;

/**
 * Test helper exposing the protected members of OutFont.
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @phpstan-import-type TFontData from \Com\Tecnick\Pdf\Font\Load
 */
class OutputTestOutFont extends \Com\Tecnick\Pdf\Font\OutFont
{
    /**
     * The output methods escape the PDF name objects through the encrypt object, so it
     * is provided here as Output does.
     *
     * @throws EncException
     */
    public function __construct()
    {
        $this->enc = new Encrypt();
    }

    /**
     * @param TFontData $font
     */
    public function runUniToCid(array &$font, int $cidoffset): void
    {
        $this->uniToCid($font, $cidoffset);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     */
    public function runGetFontFullPath(string $fontdir, string $file): string
    {
        return $this->getFontFullPath($fontdir, $file);
    }

    public function runGetKeyValOut(string $key, mixed $val): string
    {
        return $this->getKeyValOut($key, $val);
    }

    public function runGetUtf16beHex(int $ord): string
    {
        return $this->getUtf16beHex($ord);
    }

    /**
     * @param TFontData $font
     */
    public function runGetTrueType(array $font, int $pon = 1): string
    {
        $this->pon = $pon;

        return $this->getTrueType($font);
    }

    /**
     * @param TFontData $font
     *
     * @throws EncException
     * @throws \Com\Tecnick\Pdf\Font\Exception
     */
    public function runGetTrueTypeUnicode(array $font, int $pon = 1): string
    {
        $this->pon = $pon;

        return $this->getTrueTypeUnicode($font);
    }
}
