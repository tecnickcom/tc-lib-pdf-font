<?php

/**
 * FontDescriptorFlagsTest.php
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
 * The Symbolic and Nonsymbolic descriptor flags are mutually exclusive.
 *
 * ISO 32000-1 Table 123: bit 3 (Symbolic, 4) and bit 6 (Nonsymbolic, 32) shall not both be
 * set nor both be clear.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FontDescriptorFlagsTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/';

    private const SYMBOLIC = 4;

    private const NONSYMBOLIC = 32;

    /**
     * Returns the descriptor flags of an imported font.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    private function importFlags(string $file, int $flags = 32): int
    {
        $this->setupTest();
        $import = new Import(\dirname(__DIR__) . self::MIRROR . $file, '', '', '', $flags);

        return $this->readFlags($import->getFontName());
    }

    /**
     * Returns the descriptor flags stored in the definition file of a font.
     */
    private function readFlags(string $name): int
    {
        /** @var array<array-key, mixed> $decoded */
        $decoded = \json_decode((string) \file_get_contents($this->getFontPath() . $name . '.json'), true);

        /** @var array<array-key, mixed> $desc */
        $desc = $decoded['desc'] ?? [];

        /** @var int $value */
        $value = $desc['Flags'] ?? null;
        $this->assertIsInt($value);

        return $value;
    }

    /**
     * Asserts that exactly one of the two bits is set.
     */
    private function assertExclusive(int $flags, bool $symbolic): void
    {
        $this->assertSame($symbolic ? self::SYMBOLIC : 0, $flags & self::SYMBOLIC, 'symbolic bit of ' . $flags);
        $this->assertSame(
            $symbolic ? 0 : self::NONSYMBOLIC,
            $flags & self::NONSYMBOLIC,
            'nonsymbolic bit of ' . $flags,
        );
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testASymbolicCoreFontDeclaresOnlyTheSymbolicBit(): void
    {
        $this->assertExclusive($this->importFlags('core/Symbol.afm'), true);
        $this->assertExclusive($this->importFlags('core/ZapfDingbats.afm'), true);
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testATextCoreFontDeclaresOnlyTheNonsymbolicBit(): void
    {
        $this->assertExclusive($this->importFlags('core/Helvetica.afm'), false);
        // a caller asking for symbolic on a text font is corrected the same way
        $this->assertExclusive($this->importFlags('core/Helvetica.afm', 4), false);
    }

    /**
     * The Core importer decides from the AFM FontName, so the caller's request does not
     * leave the contradicting bit behind either way.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTheCallerRequestDoesNotLeaveBothBitsSet(): void
    {
        $this->assertExclusive($this->importFlags('core/Symbol.afm', 32), true);
        $this->assertExclusive($this->importFlags('core/Symbol.afm', 4), true);
        // the unrelated bits the caller asked for survive: 64 is Italic
        $this->assertSame(64, $this->importFlags('core/Symbol.afm', 64 | 32) & 64);
    }

    /**
     * A TrueType font is flagged from its file name, on the same exclusive pair.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTheFileNameDrivenSymbolicFlagIsExclusiveToo(): void
    {
        $this->setupTest();
        $source = \dirname(__DIR__) . self::MIRROR . 'freefont/FreeSerif.ttf';
        $named = $this->getFontPath() . 'testsymbolfont.ttf';
        $this->assertTrue(\copy($source, $named));

        $import = new Import($named);

        $this->assertExclusive($this->readFlags($import->getFontName()), true);
    }

    /**
     * A TrueType or Type 1 font normalizes the flags of the caller: the symbolic bit wins,
     * and a request carrying neither states the nonsymbolic one.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    public function testTheRequestOfTheCallerIsNormalizedOnAFontThatStatesNothing(): void
    {
        $this->setupTest();
        $source = \dirname(__DIR__) . self::MIRROR . 'freefont/FreeSerif.ttf';
        $named = $this->getFontPath() . 'plainfont.ttf';
        $this->assertTrue(\copy($source, $named));
        $this->assertTrue(\mkdir($this->getFontPath() . 'both/', 0o755, true));
        $this->assertTrue(\mkdir($this->getFontPath() . 'neither/', 0o755, true));

        $both = new Import($named, $this->getFontPath() . 'both/', '', '', self::SYMBOLIC | self::NONSYMBOLIC);
        $this->assertExclusive($this->readFlagsIn('both/', $both->getFontName()), true);

        $neither = new Import($named, $this->getFontPath() . 'neither/', '', '', 0);
        $this->assertExclusive($this->readFlagsIn('neither/', $neither->getFontName()), false);
    }

    /**
     * The descriptor flags are an unsigned 32-bit integer, and the value is written in the
     * definition file as it is given.
     *
     * @throws \Throwable
     */
    public function testFlagsOutsideTheUnsignedRangeAreRefused(): void
    {
        $this->setupTest();
        $source = \dirname(__DIR__) . self::MIRROR . 'freefont/FreeSerif.ttf';
        $named = $this->getFontPath() . 'rangefont.ttf';
        $this->assertTrue(\copy($source, $named));

        foreach ([-1, 0x1_0000_0000] as $flags) {
            $this->assertThrowsMessage(
                FontException::class,
                'must fit 32 unsigned bits',
                /**
                 * @throws FontException
                 * @throws FileException
                 */
                static fn(): Import => new Import($named, '', '', '', $flags),
            );
        }
    }

    /**
     * Returns the descriptor flags of a font imported in a sub-directory of the font path.
     */
    private function readFlagsIn(string $subdir, string $name): int
    {
        /** @var array<array-key, mixed> $decoded */
        $decoded = \json_decode((string) \file_get_contents($this->getFontPath() . $subdir . $name . '.json'), true);

        /** @var array<array-key, mixed> $desc */
        $desc = $decoded['desc'] ?? [];

        /** @var int $value */
        $value = $desc['Flags'] ?? null;
        $this->assertIsInt($value);

        return $value;
    }
}
