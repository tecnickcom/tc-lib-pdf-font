<?php

/**
 * RegexFailureTest.php
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
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Import\Core;
use Com\Tecnick\Pdf\Font\Import\TypeOne;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * A PCRE call fails at run time when a pattern exhausts the backtrack limit. Every such
 * call in the library reports the failure as a font exception, and each guard is exercised
 * here by shrinking the limit around the call.
 *
 * The JIT compiler does not honour pcre.backtrack_limit, so it is turned off for the
 * duration of each test.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @phpstan-import-type TFontData from \Com\Tecnick\Pdf\Font\Load
 */
class RegexFailureTest extends TestUtil
{
    /**
     * @param class-string<\Throwable> $exception
     *
     * @throws \Throwable
     */
    private function assertFailsWithLimit(int $limit, string $exception, string $needle, callable $callback): void
    {
        PcreBacktrackLimit::run($limit, function () use ($exception, $needle, $callback): void {
            $this->assertThrowsMessage($exception, $needle, $callback);
        });
    }

    // -------------------------------------------------------------------------
    // Stack::getNormalizedFontKeys
    // -------------------------------------------------------------------------

    /** @throws \Throwable */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testFontFamilySanitisationFailureIsReported(): void
    {
        $this->setupTest();
        $stack = new Stack(1);

        // the family holds characters to strip, so the first substitution has to backtrack
        $this->assertFailsWithLimit(
            1,
            FontException::class,
            'Invalid font family name',
            /** @throws FontException */
            static fn(): string => $stack->getFontFamilyName('Times New Roman!'),
        );
    }

    /** @throws \Throwable */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testFontFamilySplitFailureIsReported(): void
    {
        $this->setupTest();
        $stack = new Stack(1);

        // nothing to strip, so the sanitisation completes and the split on ',' is the
        // first call to run out of budget
        $this->assertFailsWithLimit(
            1,
            FontException::class,
            'Invalid font family name',
            /** @throws FontException */
            static fn(): string => $stack->getFontFamilyName('times,helvetica'),
        );
    }

    /** @throws \Throwable */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testFontStyleSuffixFailureIsReported(): void
    {
        $this->setupTest();
        $stack = new Stack(1);

        // the anchored style patterns are the first to backtrack on a name ending in
        // one of the style suffixes
        $this->assertFailsWithLimit(
            1,
            FontException::class,
            'Invalid font family name',
            /** @throws FontException */
            static fn(): string => $stack->getFontFamilyName('timesregular'),
        );
    }

    /** @throws \Throwable */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testFontFamilyAliasFailureIsReported(): void
    {
        $this->setupTest();
        $stack = new Stack(1);

        // the alias patterns hold an alternation, which costs more than the previous
        // calls: with a slightly larger limit they are the first to fail
        $this->assertFailsWithLimit(
            2,
            FontException::class,
            'Invalid font family name',
            /** @throws FontException */
            static fn(): string => $stack->getFontFamilyName('times'),
        );
    }

    // -------------------------------------------------------------------------
    // Import
    // -------------------------------------------------------------------------

    private function buildImport(): Import
    {
        $class = new \ReflectionClass(Import::class);

        return $class->newInstanceWithoutConstructor();
    }

    /** @throws \Throwable */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testFontFileNameSanitisationFailureIsReported(): void
    {
        $import = $this->buildImport();
        $method = new \ReflectionMethod(Import::class, 'makeFontName');

        $this->assertFailsWithLimit(
            1,
            FontException::class,
            'Invalid font file name',
            /** @throws FontException */
            static fn(): mixed => $method->invoke($import, '/tmp/my-font.ttf'),
        );
    }

    /** @throws \Throwable */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testEncodingNameSanitisationFailureIsReported(): void
    {
        $import = $this->buildImport();
        $method = new \ReflectionMethod(Import::class, 'getEncodingTable');

        $this->assertFailsWithLimit(
            1,
            FontException::class,
            'Invalid encoding name',
            /** @throws FontException */
            static fn(): mixed => $method->invoke($import, 'cp1252!'),
        );
    }

    // -------------------------------------------------------------------------
    // Import\Core and Import\TypeOne
    // -------------------------------------------------------------------------

    /** @throws \Throwable */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testAfmFontNameSanitisationFailureIsReported(): void
    {
        $class = new \ReflectionClass(Core::class);
        $core = $class->newInstanceWithoutConstructor();
        // the entries remapValues() reads, with a name holding a character to strip
        $fdt = [
            'Ascender' => 700,
            'Descender' => -200,
            'FontBBox' => [0, -200, 1000, 700],
            'FontName' => 'Helvetica Bold!',
            'FullName' => '',
            'ItalicAngle' => 0,
            'StdHW' => 0,
            'StdVW' => 0,
            'UnderlinePosition' => -100,
            'UnderlineThickness' => 50,
            // read by the weight-derived stem fallback, which the zeroed StdVW/StdHW select
            'Weight' => '',
            'weight' => '',
        ];
        (new \ReflectionProperty(Core::class, 'fdt'))->setValue($core, $fdt);
        $method = new \ReflectionMethod(Core::class, 'remapValues');

        $this->assertFailsWithLimit(
            1,
            FontException::class,
            'Invalid font name',
            /** @throws FontException */
            static fn(): mixed => $method->invoke($core),
        );
    }

    /** @throws \Throwable */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testTypeOneFontNameScanFailureIsReported(): void
    {
        $class = new \ReflectionClass(TypeOne::class);
        $typeOne = $class->newInstanceWithoutConstructor();
        // neither name pattern can complete, so no name is extracted at all
        (new \ReflectionProperty(TypeOne::class, 'font'))->setValue($typeOne, '/FontName /Foo+Bar def');
        $method = new \ReflectionMethod(TypeOne::class, 'extractFontInfo');

        $this->assertFailsWithLimit(
            1,
            FontException::class,
            'Unable to extract font name',
            /** @throws FontException */
            static fn(): mixed => $method->invoke($typeOne),
        );
    }

    /** @throws \Throwable */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testTypeOneCharstringScanFailureIsReported(): void
    {
        $class = new \ReflectionClass(TypeOne::class);
        $typeOne = $class->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(TypeOne::class, 'getCharstringData');
        // The entry header is matched with possessive quantifiers, so the engine gives up
        // on the budget it spends walking the subject rather than on backtracking.
        $eplain = "/CharStrings 1 dict dup begin\n/a 200 RD " . \str_repeat("\x01", 300) . " ND\nend";

        $this->assertFailsWithLimit(
            1,
            FontException::class,
            'Unable to parse the Type1 charstrings',
            /** @throws FontException */
            static fn(): mixed => $method->invoke($typeOne, $eplain),
        );
    }
}
