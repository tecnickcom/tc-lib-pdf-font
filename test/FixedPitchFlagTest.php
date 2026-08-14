<?php

/**
 * FixedPitchFlagTest.php
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

use Com\Tecnick\File\Exception as FileException;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Import;

/**
 * The 'post' table settles the FixedPitch flag, in both directions.
 *
 * Import::initFlags() guesses it from substrings of the file name ('mono', 'courier',
 * 'fixed') before the program is read, and getPostData() then clears or raises the bit
 * according to what the table declares.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FixedPitchFlagTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/';

    private const FIXED_PITCH = 1;

    /**
     * Import the given mirror font under a name of our choosing and return its flags.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    private function flagsFor(string $source, string $name): int
    {
        $dir = $this->getFontPath() . 'src';
        if (!\is_dir($dir)) {
            \mkdir($dir, 0o755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . $name;
        \copy(\dirname(__DIR__) . self::MIRROR . $source, $path);

        $import = new Import($path, $this->getFontPath());
        $decoded = $this->decodeDefinition($this->getFontPath() . $import->getFontName() . '.json');

        return $this->intMember($this->arrayMember($decoded, 'desc'), 'Flags');
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAProportionalFontNamedLikeAMonospacedOneIsNotFlagged(): void
    {
        $this->setupTest();
        // FreeSans is proportional, and its name carries one of the guessed substrings
        $flags = $this->flagsFor('freefont/FreeSans.ttf', 'monofreesans.ttf');

        $this->assertSame(0, $flags & self::FIXED_PITCH, 'flags were ' . $flags);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAMonospacedFontIsFlaggedWhateverItsNameSays(): void
    {
        $this->setupTest();
        $flags = $this->flagsFor('freefont/FreeMono.ttf', 'plainname.ttf');

        $this->assertSame(self::FIXED_PITCH, $flags & self::FIXED_PITCH, 'flags were ' . $flags);
    }
}
