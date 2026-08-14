<?php

/**
 * ArtifactNameSanitizationTest.php
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
 * The name of a stored artifact is reduced to a safe alphabet, extension included.
 *
 * Linked mode carries the extension of the input file over to the symbolic link it creates,
 * and the definition file is hand-built JSON, so the extension is reduced to lowercase
 * alphanumeric characters before it reaches either.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class ArtifactNameSanitizationTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/freefont/FreeSans.ttf';

    /**
     * Copy the mirror font under a name carrying the given extension.
     */
    private function stageFont(string $extension): string
    {
        $dir = $this->getFontPath() . 'src';
        \mkdir($dir, 0o755, true);
        $path = $dir . DIRECTORY_SEPARATOR . 'myfont.' . $extension;
        \copy(\dirname(__DIR__) . self::MIRROR, $path);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function readDefinition(string $name): array
    {
        return $this->decodeDefinition($this->getFontPath() . $name . '.json');
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAnExtensionCannotInjectMembersIntoTheDefinitionFile(): void
    {
        $this->setupTest();
        $path = $this->stageFont('ttf","name":"PWNED');

        $import = new Import($path, $this->getFontPath(), '', '', 32, 3, 1, true);
        $decoded = $this->readDefinition($import->getFontName());

        // the name read from the font program wins, not the one the file name carried
        $this->assertSame('FreeSans', $this->stringMember($decoded, 'name'));
        $this->assertSame('myfont.ttfnamepwned', $this->stringMember($decoded, 'file'));
    }

    /**
     * The recorded name has to be the one on disk, or the program cannot be embedded.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTheRecordedFileNameIsTheLinkThatWasCreated(): void
    {
        $this->setupTest();
        $path = $this->stageFont('ttf","name":"PWNED');

        $import = new Import($path, $this->getFontPath(), '', '', 32, 3, 1, true);
        $decoded = $this->readDefinition($import->getFontName());

        $this->assertTrue(
            \is_link($this->getFontPath() . $this->stringMember($decoded, 'file')),
            'the definition file must name the link that was created',
        );
    }

    /**
     * An ordinary extension is carried over unchanged, so that the stored program keeps
     * announcing what it is.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testAnOrdinaryExtensionIsKept(): void
    {
        $this->setupTest();
        $path = $this->stageFont('TTF');

        $import = new Import($path, $this->getFontPath(), '', '', 32, 3, 1, true);
        $decoded = $this->readDefinition($import->getFontName());

        $this->assertSame('myfont.ttf', $this->stringMember($decoded, 'file'));
    }
}
