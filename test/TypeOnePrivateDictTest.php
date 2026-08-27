<?php

/**
 * TypeOnePrivateDictTest.php
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

/**
 * What the Private dict of a Type 1 font states wins over what its blue zones suggest.
 *
 * '/BlueValues' only approximates the cap height and the x-height, so it applies only when
 * the dict declares no '/CapHeight'.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TypeOnePrivateDictTest extends TestUtil
{
    private function setProp(object $obj, string $name, mixed $value): void
    {
        $prop = new \ReflectionProperty($obj, $name);
        $prop->setValue($obj, $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function heightsAfter(string $eplain): array
    {
        $class = new \ReflectionClass(TypeOneEplainHarness::class);
        $instance = $class->newInstanceWithoutConstructor();
        $fdt = \Com\Tecnick\Pdf\Font\Load::DEFAULT_DATA;
        $fdt['Ascent'] = 700;
        $fdt['Descent'] = -200;
        $this->setProp($instance, 'fdt', $fdt);
        $this->setProp($instance, 'font', '');
        $instance->setEplain($eplain);

        $instance->runExtractEplainInfo();

        /** @var mixed $fdt */
        $fdt = (new \ReflectionProperty($instance, 'fdt'))->getValue($instance);
        $this->assertIsArray($fdt);

        /** @var array<string, mixed> $fdt */
        return $fdt;
    }

    /**
     * '/StdVW' and '/StdHW' are arrays, '/CapHeight' is a plain number: a font declares it
     * as '/CapHeight 700 def'. Both spellings are read.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function provideCapHeightSpelling(): array
    {
        return [
            'as a plain number, the way a font writes it' => ['/CapHeight 712 def', 712],
            'as a bracketed array' => ['/CapHeight [712] def', 712],
            'with no space before the value' => ['/CapHeight  712 def', 712],
            'negative' => ['/CapHeight -712 def', -712],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideCapHeightSpelling')]
    public function testAnExplicitCapHeightSurvivesTheBlueValuesFallback(string $spelling, int $expected): void
    {
        $fdt = $this->heightsAfter($spelling . ' /BlueValues [-20 0 480 490 660 670] def');

        $this->assertSame($expected, $this->intMember($fdt, 'CapHeight'));
        // the x-height keeps the value derived alongside it, not the blue zone
        $this->assertSame(500, $this->intMember($fdt, 'XHeight'));
    }

    public function testTheBlueValuesFallbackAppliesWhenNoCapHeightIsDeclared(): void
    {
        $fdt = $this->heightsAfter('/BlueValues [-20 0 480 490 660 670] def');

        $this->assertSame(660, $this->intMember($fdt, 'CapHeight'));
        $this->assertSame(480, $this->intMember($fdt, 'XHeight'));
    }

    /**
     * Fewer than six blue values do not describe both zones, so neither is inferred.
     */
    public function testAShortBlueValuesArrayIsIgnored(): void
    {
        $fdt = $this->heightsAfter('/BlueValues [-20 0 480 490] def');

        $this->assertSame(700, $this->intMember($fdt, 'CapHeight'));
        $this->assertSame(500, $this->intMember($fdt, 'XHeight'));
    }
}
