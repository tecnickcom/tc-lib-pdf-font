<?php

/**
 * FileWriterTest.php
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

use Com\Tecnick\File\File as ObjFile;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\FileWriter;
use Com\Tecnick\Pdf\Font\FontPaths;

/**
 * Storage of the font artifacts.
 *
 * A short write is reported as an exception.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FileWriterTest extends TestUtil
{
    /**
     * @throws \Throwable
     */
    public function testWriteStoresTheWholeContent(): void
    {
        $this->setupTest();
        $file = $this->getFontPath() . 'artifact.bin';
        $data = \str_repeat("\x01\x02", 1024);

        FileWriter::write(new ObjFile(allowedPaths: [$this->getFontPath()]), $file, $data);

        $this->assertSame($data, \file_get_contents($file));
    }

    /**
     * @throws \Throwable
     */
    public function testWriteReportsAFailedWrite(): void
    {
        $this->setupTest();
        $file = $this->getFontPath() . 'artifact.bin';
        \file_put_contents($file, '');

        $raised = [];
        \set_error_handler(static function (int $level, string $message) use (&$raised): bool {
            unset($level);
            $raised[] = $message;
            return true;
        });

        try {
            $this->assertThrowsMessage(
                FontException::class,
                // the reason reported by the stream is carried into the message
                'Unable to write the file: ' . $file . ' (fwrite()',
                /** @throws \Throwable */
                static fn() => FileWriter::write(new ReadOnlyFileHelper(), $file, 'data'),
            );
        } finally {
            \restore_error_handler();
        }

        $this->assertSame([], $raised, 'the warning of the failed write must not escape');
        $this->assertSame('', \file_get_contents($file));
    }

    /**
     * An unwritable path is reported by the file helper before anything is stored.
     *
     * @throws \Throwable
     */
    public function testWriteReportsAFileItCannotOpen(): void
    {
        $this->setupTest();
        $helper = new ObjFile(allowedPaths: FontPaths::buildAllowedPaths());

        $this->assertThrowsMessage(
            \Com\Tecnick\File\Exception::class,
            'invalid file',
            /** @throws \Throwable */
            static fn() => FileWriter::write($helper, '/nonexistent/path/artifact.bin', 'data'),
        );
    }
}
