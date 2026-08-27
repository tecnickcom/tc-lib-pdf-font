<?php

/**
 * FontProgramInflationBoundTest.php
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
use Com\Tecnick\Pdf\Encrypt\Encrypt;
use Com\Tecnick\Pdf\Encrypt\Exception as EncException;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Output;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * The stored font program is inflated under the size the definition file declares for it.
 *
 * Subsetting uncompresses the '.z' artifact bounded by the lengths the definition file
 * records for the program, and rejects a stream expanding past them.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FontProgramInflationBoundTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/freefont/FreeSans.ttf';

    /**
     * Write a TrueTypeUnicode definition and the compressed program it names.
     *
     * @param string $program      Raw font program to store.
     * @param int    $originalsize Size the definition file declares for it.
     */
    private function writeFont(string $key, string $program, int $originalsize): void
    {
        \file_put_contents($this->getFontPath() . $key . '.z', (string) \gzcompress($program, 9));
        \file_put_contents(
            $this->getFontPath() . $key . '.json',
            '{"type":"TrueTypeUnicode","name":"'
            . $key
            . '","dw":600'
            . ',"desc":{"FontBBox":"[0 -200 1000 800]","Ascent":800,"Descent":-200}'
            . ',"cw":{"65":400},"file":"'
            . $key
            . '.z","originalsize":'
            . $originalsize
            . '}',
        );
    }

    /**
     * @throws EncException
     * @throws FileException
     * @throws FontException
     */
    private function emit(string $key): string
    {
        $objnum = 1;
        $stack = new Stack(1, true);
        $stack->insert($objnum, $key, '', 10, 0, 1, '', true);
        $output = new Output($stack->getFonts(), $objnum, new Encrypt());

        return $output->getFontsBlock();
    }

    /**
     * A font declaring its real size is embedded.
     *
     * @throws EncException
     * @throws FileException
     * @throws FontException
     */
    public function testARealProgramIsAcceptedAtItsDeclaredSize(): void
    {
        $this->setupTest();
        $program = (string) \file_get_contents(\dirname(__DIR__) . self::MIRROR);
        $this->writeFont('boundok', $program, \strlen($program));

        $this->assertStringContainsString('/FontFile2', $this->emit('boundok'));
    }

    /**
     * @throws EncException
     * @throws FileException
     * @throws FontException
     */
    public function testAProgramInflatingPastItsDeclaredSizeIsRefused(): void
    {
        $this->setupTest();
        // 50 MB of a single byte compresses to a few tens of kilobytes
        $this->writeFont('bomb', \str_repeat('A', 50_000_000), 4096);

        $this->assertThrowsMessage(
            FontException::class,
            'Unable to uncompress font file',
            /** @throws \Throwable */
            fn() => $this->emit('bomb'),
        );
    }

    /**
     * A definition file that declares no size at all keeps the unbounded behaviour, rather
     * than refusing every font whose lengths are missing.
     *
     * @throws EncException
     * @throws FileException
     * @throws FontException
     */
    public function testAFontDeclaringNoSizeIsStillRead(): void
    {
        $this->setupTest();
        $program = (string) \file_get_contents(\dirname(__DIR__) . self::MIRROR);
        $this->writeFont('nosize', $program, 0);

        $this->assertStringContainsString('/FontFile2', $this->emit('nosize'));
    }
}
