<?php

/**
 * TypeOneSegmentsTest.php
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

use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Zlib;

/**
 * Segment structure of a binary Type1 (PFB) font.
 *
 * A PFB file is a sequence of [0x80, type, uint32 length] segments: the eexec data may span
 * several binary segments, and an ASCII trailer closes the font. Every segment is read, so
 * the whole glyph program is stored.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TypeOneSegmentsTest extends TestUtil
{
    private const MIRROR = 'util/vendor/tecnickcom/tc-font-mirror/pdfa/pfb/PDFAHelvetica.pfb';

    /**
     * Build a PFB segment header.
     */
    private function segment(int $type, string $payload): string
    {
        return "\x80" . \chr($type) . \pack('V', \strlen($payload)) . $payload;
    }

    /**
     * Split the bundled PFB font into its clear-text and binary portions.
     *
     * @return array{0: string, 1: string}
     *
     * @throws \Throwable
     */
    private function readParts(): array
    {
        $font = \file_get_contents(\dirname(__DIR__) . '/' . self::MIRROR);
        $this->assertIsString($font);

        $head = \unpack('Cmarker/Ctype/Vsize', \substr($font, 0, 6));
        $this->assertIsArray($head);
        $size1 = (int) ($head['size'] ?? 0);

        $second = \unpack('Cmarker/Ctype/Vsize', \substr($font, 6 + $size1, 6));
        $this->assertIsArray($second);
        $size2 = (int) ($second['size'] ?? 0);

        return [\substr($font, 6, $size1), \substr($font, 12 + $size1, $size2)];
    }

    /**
     * Import a font program and return the segment sizes, the widths and the stored bytes.
     *
     * @return array{0: array{size1: int, size2: int, cw: array<int, int>}, 1: string}
     *
     * @throws \Throwable
     */
    private function importProgram(string $program, string $name): array
    {
        $file = $this->getFontPath() . $name . '.pfb';
        \file_put_contents($file, $program);

        $import = new Import($file, $this->getFontPath());
        $metrics = $import->getFontMetrics();

        $stored = \file_get_contents($this->getFontPath() . $metrics['file']);
        $this->assertIsString($stored);
        $uncompressed = Zlib::uncompress($stored);
        $this->assertIsString($uncompressed);

        return [
            [
                'size1' => $metrics['size1'],
                'size2' => $metrics['size2'],
                'cw' => $metrics['cw'],
            ],
            $uncompressed,
        ];
    }

    /**
     * @throws \Throwable
     */
    public function testTheEexecDataOfEverySegmentIsStored(): void
    {
        $this->setupTest();
        [$clear, $binary] = $this->readParts();
        $half = \intdiv(\strlen($binary), 2);

        // the same font, written as a single binary segment and as two of them, closed by
        // the ASCII trailer and the end-of-file marker a complete PFB carries
        $trailer = \str_repeat('0', 512) . "\ncleartomark\n";
        $single = $this->segment(1, $clear) . $this->segment(2, $binary) . $this->segment(1, $trailer) . "\x80\x03";
        $split =
            $this->segment(1, $clear)
            . $this->segment(2, \substr($binary, 0, $half))
            . $this->segment(2, \substr($binary, $half))
            . $this->segment(1, $trailer)
            . "\x80\x03";

        // a font closed by the end-of-file marker alone, with no ASCII trailer
        $bare = $this->segment(1, $clear) . $this->segment(2, $binary) . "\x80\x03";

        [$singleMetrics, $singleData] = $this->importProgram($single, 'single');
        [$splitMetrics, $splitData] = $this->importProgram($split, 'split');
        [$bareMetrics, $bareData] = $this->importProgram($bare, 'bare');

        $this->assertSame($singleMetrics['size2'], $bareMetrics['size2']);
        $this->assertSame($singleData, $bareData);

        $this->assertSame(\strlen($clear), $singleMetrics['size1']);
        $this->assertSame(\strlen($binary), $singleMetrics['size2']);
        $this->assertSame($singleMetrics['size1'], $splitMetrics['size1']);
        $this->assertSame($singleMetrics['size2'], $splitMetrics['size2'], 'both binary segments are read');
        $this->assertSame($singleData, $splitData, 'the stored program does not depend on the segmentation');
        $this->assertSame($clear . $binary, $splitData, 'the ASCII trailer is not embedded');
        $this->assertNotEmpty($singleMetrics['cw'], 'the charstrings are parsed');
        $this->assertSame($singleMetrics['cw'], $splitMetrics['cw']);
    }

    /**
     * The PostScript directives are read from the clear text portion alone. The eexec
     * encrypted portion holds no readable directive, and a byte sequence in it that happens
     * to read as one must not enter the encoding map of the font.
     *
     * @throws \Throwable
     */
    public function testAnEncodingDirectiveInTheEncryptedPortionIsNotRead(): void
    {
        $this->setupTest();
        [$clear, $binary] = $this->readParts();

        $plain = $this->segment(1, $clear) . $this->segment(2, $binary) . "\x80\x03";
        // 'micro' is a glyph of this font that the declared encoding does not name, so the
        // built-in array of the program is what gives it a code, and 129 is a free one
        $injected = $this->segment(1, $clear) . $this->segment(2, $binary . ' dup 129 /micro put ') . "\x80\x03";

        [$plainMetrics] = $this->importProgram($plain, 'plain');
        [$injectedMetrics] = $this->importProgram($injected, 'injected');

        $this->assertArrayNotHasKey(129, $plainMetrics['cw'], 'the code is free in this font');
        $this->assertArrayNotHasKey(129, $injectedMetrics['cw']);
        $this->assertSame($plainMetrics['cw'], $injectedMetrics['cw']);
    }
}
