<?php

/**
 * TypeOneCharstringWidthTest.php
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
use Com\Tecnick\Pdf\Font\Import\TypeOne;
use Com\Tecnick\Pdf\Font\Load;

/**
 * Both Type 1 width commands are recognised, and an escaped command is consumed as the two
 * byte sequence it is.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TypeOneCharstringWidthTest extends TestUtil
{
    private function buildTypeOne(): TypeOne
    {
        $class = new \ReflectionClass(TypeOne::class);
        $instance = $class->newInstanceWithoutConstructor();
        (new \ReflectionProperty(TypeOne::class, 'fdt'))->setValue($instance, Load::DEFAULT_DATA);
        (new \ReflectionProperty(TypeOne::class, 'font'))->setValue($instance, '');

        return $instance;
    }

    /**
     * Decode a whole charstring, the way TypeOne::process() drives decodeNumber().
     *
     * @param array<int, int> $ccom Decrypted charstring bytes.
     *
     * @return array{0: array<int, int>, 1: array<int, int>} The decoded values and the widths.
     *
     * @throws \ReflectionException
     */
    private function decode(array $ccom, int $cid = 7): array
    {
        $instance = $this->buildTypeOne();
        $method = new \ReflectionMethod(TypeOne::class, 'decodeNumber');
        $cdec = [];
        $cwidths = [];
        $cck = 0;
        $idx = 0;
        $len = \count($ccom);
        while ($idx < $len) {
            // only the last two arguments are taken by reference
            /** @var mixed $next */
            $next = $method->invokeArgs($instance, [$idx, $cck, $cid, $ccom, &$cdec, &$cwidths]);
            $this->assertIsInt($next);
            $idx = $next;
            ++$cck;
        }

        return [$this->intMapOf($cdec), $this->intMapOf($cwidths)];
    }

    /**
     * @return array<int, int>
     */
    private function intMapOf(mixed $value): array
    {
        $this->assertIsArray($value);

        return $this->intMap($value);
    }

    /**
     * 'sbx sby wx wy sbw' states the width in the third of its four operands. Without it
     * the glyph records no width at all and falls back to /MissingWidth.
     *
     * @throws \Throwable
     */
    public function testSbwRecordsTheHorizontalWidth(): void
    {
        // 100 0 500 0 sbw, with the operands in the compact 32..246 encoding (value - 139)
        [, $cwidths] = $this->decode([139 + 100, 139, 255, 0, 0, 0x01, 0xF4, 139, 12, 7]);

        $this->assertSame(500, $cwidths[7] ?? null);
    }

    /**
     * 'sbx wx hsbw' states the width in the second of its two operands.
     *
     * @throws \Throwable
     */
    public function testHsbwRecordsTheWidth(): void
    {
        [, $cwidths] = $this->decode([139 + 50, 255, 0, 0, 0x01, 0x2C, 13]);

        $this->assertSame(300, $cwidths[7] ?? null);
    }

    /**
     * An escaped command is the pair '12 n'. Decoding the second byte as a value of its own
     * leaves the decoder one byte out of phase for the rest of the charstring, so the two
     * are consumed together and yield a single decoded entry.
     *
     * @throws \Throwable
     */
    public function testAnEscapedCommandIsConsumedAsOneOperator(): void
    {
        // 100 200 div, then 30 40 rlineto: the escape must not shift what follows it
        [$cdec] = $this->decode([139 + 100, 139 + 100, 12, 12, 139 + 30, 139 + 40, 5]);

        $this->assertSame([100, 100, 12, 30, 40, 5], \array_values($cdec));
    }

    /**
     * A charstring ending on the escape byte carries no command: the truncation is reported
     * so that the caller can move on to the next glyph.
     *
     * @throws \Throwable
     */
    public function testATruncatedEscapedCommandIsReported(): void
    {
        $this->assertThrowsMessage(
            FontException::class,
            'Truncated Type1 charstring escaped command',
            fn() => $this->decode([139 + 100, 12]),
        );
    }

    /**
     * The escape byte on its own is not the hsbw command, whatever the operand stack holds.
     *
     * @throws \Throwable
     */
    public function testAnEscapedCommandOtherThanSbwRecordsNoWidth(): void
    {
        // 100 200 seac (12 6) records no width
        [, $cwidths] = $this->decode([139 + 100, 139 + 100, 12, 6]);

        $this->assertSame([], $cwidths);
    }
}
