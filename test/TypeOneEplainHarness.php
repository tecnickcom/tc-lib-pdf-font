<?php

/**
 * TypeOneEplainHarness.php
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
 * Reads the Private dict of a Type 1 font from a plain string.
 *
 * extractEplainInfo() decrypts the eexec portion itself, so the fixtures of the tests below
 * are supplied in its place: what they exercise is how the entries of the decrypted dict are
 * read, not the decryption.
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
class TypeOneEplainHarness extends \Com\Tecnick\Pdf\Font\Import\TypeOne
{
    private string $eplain = '';

    public function setEplain(string $eplain): void
    {
        $this->eplain = $eplain;
    }

    #[\Override]
    protected function getEplain(): string
    {
        return $this->eplain;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function runExtractEplainInfo(): array
    {
        return $this->extractEplainInfo();
    }
}
