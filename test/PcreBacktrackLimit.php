<?php

/**
 * PcreBacktrackLimit.php
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
 * Test helper running a callback with a PCRE backtrack limit small enough to make the
 * engine give up, so that the failure branch of a preg_* call can be reached.
 *
 * The limit is a runtime setting rather than a fixture, and the JIT compiler does not
 * honour it, so both are set around the call and restored afterwards.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class PcreBacktrackLimit
{
    /**
     * @param int      $limit    Value of pcre.backtrack_limit. The cheapest patterns of the
     *                           library still complete with a limit of a few units, so the
     *                           value selects which call is the first to fail.
     * @param callable $callback Code to run.
     *
     * @throws \Throwable whatever the callback raises.
     */
    public static function run(int $limit, callable $callback): void
    {
        $jit = \ini_get('pcre.jit');
        $backtrack = \ini_get('pcre.backtrack_limit');
        \ini_set('pcre.jit', '0');
        \ini_set('pcre.backtrack_limit', (string) $limit);

        try {
            $callback();
        } finally {
            \ini_set('pcre.jit', $jit === false ? '1' : $jit);
            \ini_set('pcre.backtrack_limit', $backtrack === false ? '1000000' : $backtrack);
        }
    }
}
