<?php

declare(strict_types=1);

/**
 * Zlib.php
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

namespace Com\Tecnick\Pdf\Font;

use Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Zlib
 *
 * Compression and decompression of the stored font artifacts.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
final class Zlib
{
    /**
     * Uncompress a zlib stream.
     *
     * gzuncompress() emits an E_WARNING before returning false for a corrupt stream, which
     * is silenced here: the callers turn that false into a FontException carrying the
     * offending file name.
     *
     * @param string $data      Compressed data.
     * @param int    $maxLength Maximum size of the uncompressed data, or 0 for no limit.
     *                          A stream expanding past it is reported as invalid.
     *
     * @return string|false The uncompressed data, or false when the stream is not valid.
     */
    public static function uncompress(string $data, int $maxLength = 0): string|false
    {
        \set_error_handler(static fn(): bool => true, E_WARNING);

        try {
            return $maxLength > 0 ? \gzuncompress($data, $maxLength) : \gzuncompress($data);
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * Compress data as a zlib stream.
     *
     * Every font artifact and PDF stream of this library is deflated here. Any warning
     * gzcompress() may emit is silenced, as in uncompress().
     *
     * @param string $data    Data to compress.
     * @param string $message Message of the exception raised when the data cannot be compressed.
     * @param int    $level   Compression level (-1 for the zlib default, 0 to 9). Any other
     *                        value is refused here, as gzcompress() would raise a ValueError.
     *
     * @return string The compressed data.
     *
     * @throws FontException if the data cannot be compressed.
     */
    public static function compress(string $data, string $message, int $level = -1): string
    {
        if ($level < -1 || $level > 9) {
            // gzcompress() raises a ValueError for any other level, which is not the
            // exception type this library contracts
            throw new FontException($message . ': invalid compression level ' . $level);
        }

        \set_error_handler(static fn(): bool => true, E_WARNING);

        try {
            $compressed = \gzcompress($data, $level);
        } finally {
            \restore_error_handler();
        }

        // the only input zlib rejects is the compression level refused above; every other
        // error condition it reports ends the process rather than returning false
        /** @var string $compressed */
        return $compressed;
    }
}
