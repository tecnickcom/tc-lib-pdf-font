<?php

/**
 * ZlibTest.php
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

use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Zlib;

/**
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
class ZlibTest extends TestUtil
{
    /** @throws FontException */
    public function testCompressRoundTrip(): void
    {
        $data = \str_repeat('font program ', 100);

        $this->assertSame($data, Zlib::uncompress(Zlib::compress($data, 'unused')));
    }

    /** @throws \Throwable */
    public function testCompressReportsAnInvalidCompressionLevel(): void
    {
        // gzcompress() rejects any level outside -1..9, and emits a warning while doing so:
        // the failure has to surface as the exception type the library contracts.
        $this->assertThrowsMessage(
            FontException::class,
            'cannot deflate',
            /** @throws FontException */
            static fn(): string => Zlib::compress('font program', 'cannot deflate', 99),
        );
    }

    public function testUncompressReportsACorruptStream(): void
    {
        $this->assertFalse(Zlib::uncompress('not a zlib stream'));
    }

    /** @throws FontException */
    public function testUncompressRejectsAStreamLargerThanTheGivenMaximum(): void
    {
        // a few compressed kilobytes expand to 2 MB: without the bound the memory is
        // committed before anyone can look at the size
        $bomb = Zlib::compress(\str_repeat("\x00", 2_000_000), 'unused');

        $this->assertFalse(Zlib::uncompress($bomb, 131_072));
        // the same stream is returned in full when no maximum is requested
        $this->assertSame(2_000_000, \strlen((string) Zlib::uncompress($bomb)));
    }
}
