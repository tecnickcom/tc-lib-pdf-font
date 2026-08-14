<?php

/**
 * FontPathsTest.php
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

use Com\Tecnick\Pdf\Font\FontPaths;

/**
 * Font file lookup rules.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FontPathsTest extends TestUtil
{
    public function testFindFontFileIgnoresAnEmptyFileName(): void
    {
        $this->assertSame('', FontPaths::findFontFile(\dirname(__DIR__), ''));
    }

    /**
     * An empty directory entry is skipped: joined with the file name it would probe the
     * filesystem root.
     */
    public function testFindFontFileNeverProbesTheFilesystemRoot(): void
    {
        $this->assertSame('', FontPaths::findFontFile('', 'etc'));
    }

    /**
     * is_readable() is true for a directory as well, so the lookup also requires a
     * regular file.
     */
    public function testFindFontFileIgnoresADirectoryWithTheRequestedName(): void
    {
        $this->assertSame('', FontPaths::findFontFile(\dirname(__DIR__), 'src'));
    }

    public function testFindFontFileReturnsAnExistingFile(): void
    {
        $found = FontPaths::findFontFile(\dirname(__DIR__), 'composer.json');

        $this->assertStringEndsWith('composer.json', $found);
        $this->assertFileExists($found);
    }

    public function testFindFontFileReturnsAnEmptyStringForAMissingFile(): void
    {
        $this->assertSame('', FontPaths::findFontFile(\dirname(__DIR__), 'no-such-font.json'));
    }
}
