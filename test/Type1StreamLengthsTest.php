<?php

/**
 * Type1StreamLengthsTest.php
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

use Com\Tecnick\Pdf\Encrypt\Encrypt;
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Output;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * Segment lengths of an embedded Type1 program.
 *
 * A Type1 stream declares the length of the clear text portion as /Length1 and the length
 * of the eexec encrypted one as /Length2, whichever way the program is stored.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class Type1StreamLengthsTest extends TestUtil
{
    private const MIRROR = 'util/vendor/tecnickcom/tc-font-mirror/pdfa/pfb/PDFASymbol.pfb';

    /**
     * Import the bundled Type1 font and return its definition.
     *
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    private function importSymbol(): array
    {
        $this->setupTest();
        new Import(\dirname(__DIR__) . '/' . self::MIRROR, $this->getFontPath(), 'Type1', 'symbol');

        return $this->decodeDefinition($this->getFontPath() . 'pdfasymbol.json');
    }

    /**
     * The declared /Length1 and /Length2 of the emitted stream.
     *
     * @param array<string, mixed> $definition Font definition.
     *
     * @throws \Throwable
     */
    private function expectedLengths(array $definition): string
    {
        return (
            '/Length1 '
            . $this->intMember($definition, 'size1')
            . ' /Length2 '
            . $this->intMember($definition, 'size2')
            . ' /Length3 0'
        );
    }

    /**
     * Emit the fonts block of the imported font.
     *
     * @throws \Throwable
     */
    private function buildFontsBlock(): string
    {
        $objnum = 1;
        $stack = new Stack(1);
        $stack->add($objnum, 'pdfasymbol');

        $reflector = new \ReflectionClass(Encrypt::class);
        $encrypt = $reflector->newInstanceWithoutConstructor();
        \assert($encrypt instanceof Encrypt, 'the Encrypt stub must be usable');

        return (new Output($stack->getFonts(), $objnum, $encrypt, null))->getFontsBlock();
    }

    /** @throws \Throwable */
    public function testTheStoredProgramDeclaresTheSegmentLengths(): void
    {
        $definition = $this->importSymbol();

        $this->assertStringContainsString($this->expectedLengths($definition), $this->buildFontsBlock());
    }

    /**
     * The same lengths are declared when the definition points at an uncompressed program,
     * whose size is the sum of the two segments and not the length of either of them.
     *
     * @throws \Throwable
     */
    public function testAnUncompressedProgramDeclaresTheSameSegmentLengths(): void
    {
        $definition = $this->importSymbol();

        $stored = \file_get_contents($this->getFontPath() . 'pdfasymbol.z');
        $this->assertIsString($stored);
        $program = \gzuncompress($stored);
        $this->assertIsString($program);
        $this->assertSame(
            $this->intMember($definition, 'size1') + $this->intMember($definition, 'size2'),
            \strlen($program),
        );

        \file_put_contents($this->getFontPath() . 'pdfasymbol.pfa', $program);
        $definition['file'] = 'pdfasymbol.pfa';
        \file_put_contents($this->getFontPath() . 'pdfasymbol.json', (string) \json_encode($definition));

        $this->assertStringContainsString($this->expectedLengths($definition), $this->buildFontsBlock());
    }
}
