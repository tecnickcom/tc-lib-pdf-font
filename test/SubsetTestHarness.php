<?php

/**
 * SubsetTestHarness.php
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
 * Test helper exposing the table-rewriting internals of Subset without running the
 * full constructor chain.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class SubsetTestHarness extends \Com\Tecnick\Pdf\Font\Subset
{
    public function __construct() {}

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     */
    public function runRemoveUnusedTables(): void
    {
        $this->removeUnusedTables();
    }

    /**
     * @return array<string, array{'checkSum': int, 'data': string, 'length': int, 'offset': int}>
     */
    public function getTable(): array
    {
        return $this->fdt['table'];
    }
}
