<?php

/**
 * ImportInternalsTest.php
 *
 * @since     2026-05-05
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
use Com\Tecnick\Pdf\Font\Import;

/**
 * Tests for the protected methods of Import invoked through reflection.
 *
 * @since     2026-05-05
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class ImportInternalsTest extends TestUtil
{
    private function buildImport(): Import
    {
        $class = new \ReflectionClass(Import::class);
        return $class->newInstanceWithoutConstructor();
    }

    /**
     * @param array<int, mixed> $args
     */
    private function callStringMethod(object $obj, string $method, array $args = []): string
    {
        $ref = new \ReflectionMethod($obj, $method);

        if (!\is_string($ref->invokeArgs($obj, $args))) {
            $this->fail('Expected string return value.');
        }

        return (string) $ref->invokeArgs($obj, $args);
    }

    private function setFdt(object $obj, mixed $fdt): void
    {
        $prop = new \ReflectionProperty($obj, 'fdt');
        $prop->setValue($obj, $fdt);
    }

    // -------------------------------------------------------------------------
    // updateCIDtoGIDmap
    // -------------------------------------------------------------------------

    /**
     * Invoke updateCIDtoGIDmap, which takes the map by reference and returns void.
     */
    private function callUpdateCIDtoGIDmap(object $obj, string $map, int $cid, int $gid): string
    {
        $ref = new \ReflectionMethod($obj, 'updateCIDtoGIDmap');
        /** @var array<int, mixed> $args */
        $args = [&$map, $cid, $gid];
        $ref->invokeArgs($obj, $args);

        /** @var string $map */
        return $map;
    }

    public function testUpdateCIDtoGIDmapSetsGlyphPairBytes(): void
    {
        $instance = $this->buildImport();
        // 65536 CID slots × 2 bytes = 131072 bytes
        $map = str_repeat("\x00", 131072);
        $result = $this->callUpdateCIDtoGIDmap($instance, $map, 65, 42);
        // gid 42 = 0x002A → high byte = 0x00, low byte = 0x2A
        $this->assertSame(0, ord($result[65 * 2]));
        $this->assertSame(42, ord($result[(65 * 2) + 1]));
        // the map keeps its size: only the two bytes of the entry are rewritten
        $this->assertSame(131072, strlen($result));
    }

    public function testUpdateCIDtoGIDmapWritesBothBytesOfALargeGid(): void
    {
        $instance = $this->buildImport();
        $map = str_repeat("\x00", 131072);
        // gid 0xABCD → high byte 0xAB, low byte 0xCD
        $result = $this->callUpdateCIDtoGIDmap($instance, $map, 0xFFFF, 0xABCD);
        $this->assertSame(0xAB, ord($result[0xFFFF * 2]));
        $this->assertSame(0xCD, ord($result[(0xFFFF * 2) + 1]));
    }

    public function testUpdateCIDtoGIDmapModifiesTheCallerMapInPlace(): void
    {
        $instance = $this->buildImport();
        $map = str_repeat("\x00", 131072);
        $ref = new \ReflectionMethod($instance, 'updateCIDtoGIDmap');
        /** @var array<int, mixed> $args */
        $args = [&$map, 7, 9];
        // the method returns void: the caller's own string must have been updated
        $this->assertNull($ref->invokeArgs($instance, $args));
        /** @var string $map */
        $this->assertSame(9, ord($map[(7 * 2) + 1]));
    }

    public function testUpdateCIDtoGIDmapIgnoresCidOutOfRange(): void
    {
        $instance = $this->buildImport();
        $map = str_repeat("\x00", 131072);
        $result = $this->callUpdateCIDtoGIDmap($instance, $map, 0x10000, 5);
        // CID 0x10000 is out of the 0..0xFFFF range → map unchanged
        $this->assertSame($map, $result);
    }

    public function testUpdateCIDtoGIDmapIgnoresGidAbove0xffff(): void
    {
        $instance = $this->buildImport();
        $map = str_repeat("\x00", 131072);
        // gid = 0x1002A is outside the representable 16-bit range → entry left as 0 (notdef)
        $result = $this->callUpdateCIDtoGIDmap($instance, $map, 0, 0x1002A);
        $this->assertSame(0, ord($result[0]));
        $this->assertSame(0, ord($result[1]));
    }

    public function testUpdateCIDtoGIDmapIgnoresNegativeGid(): void
    {
        $instance = $this->buildImport();
        $map = str_repeat("\x00", 131072);
        // gid < 0 → condition ($gid >= 0) is false → map unchanged
        $result = $this->callUpdateCIDtoGIDmap($instance, $map, 10, -1);
        $this->assertSame($map, $result);
    }

    public function testUpdateCIDtoGIDmapIgnoresNegativeCid(): void
    {
        $instance = $this->buildImport();
        $map = str_repeat("\x00", 131072);
        $result = $this->callUpdateCIDtoGIDmap($instance, $map, -1, 5);
        $this->assertSame($map, $result);
    }

    // -------------------------------------------------------------------------
    // getEncodingTable
    // -------------------------------------------------------------------------

    public function testGetEncodingTableReturnsCp1252ForType1NonSymbolic(): void
    {
        $instance = $this->buildImport();
        $this->setFdt($instance, ['type' => 'Type1', 'Flags' => 32]);
        // Flags & 4 == 0 → non-symbolic Type1 → cp1252
        $result = $this->callStringMethod($instance, 'getEncodingTable', ['']);
        $this->assertSame('cp1252', $result);
    }

    public function testGetEncodingTableReturnsEmptyForType1Symbolic(): void
    {
        $instance = $this->buildImport();
        $this->setFdt($instance, ['type' => 'Type1', 'Flags' => 4]);
        // Flags & 4 != 0 → symbolic → empty string
        $result = $this->callStringMethod($instance, 'getEncodingTable', ['']);
        $this->assertSame('', $result);
    }

    public function testGetEncodingTableReturnsEmptyForTrueTypeUnicode(): void
    {
        $instance = $this->buildImport();
        $this->setFdt($instance, ['type' => 'TrueTypeUnicode', 'Flags' => 0]);
        $result = $this->callStringMethod($instance, 'getEncodingTable', ['']);
        $this->assertSame('', $result);
    }

    public function testGetEncodingTablePassesThroughExplicitEncoding(): void
    {
        $instance = $this->buildImport();
        $this->setFdt($instance, ['type' => 'TrueType', 'Flags' => 0]);
        $result = $this->callStringMethod($instance, 'getEncodingTable', ['iso-8859-1']);
        $this->assertSame('iso-8859-1', $result);
    }

    // -------------------------------------------------------------------------
    // findOutputPath
    // -------------------------------------------------------------------------

    public function testFindOutputPathReturnsKPathFontsWhenDefined(): void
    {
        $this->setupTest();
        $instance = $this->buildImport();
        $result = $this->callStringMethod($instance, 'findOutputPath', ['']);
        $this->assertSame(constant('K_PATH_FONTS'), $result);
    }

    public function testFindOutputPathReturnsProvidedWritablePath(): void
    {
        $outdir = dirname(__DIR__) . '/target/tmptest/internals/';
        system('mkdir -p ' . $outdir);
        $instance = $this->buildImport();
        $result = $this->callStringMethod($instance, 'findOutputPath', [$outdir]);
        $this->assertSame($outdir, $result);
    }

    public function testFindOutputPathAppendsMissingTrailingSlash(): void
    {
        // every consumer builds paths as `dir . file`, so a caller-supplied directory
        // without a trailing separator must still yield a usable directory
        $outdir = dirname(__DIR__) . '/target/tmptest/internals';
        system('mkdir -p ' . $outdir);
        $instance = $this->buildImport();
        $result = $this->callStringMethod($instance, 'findOutputPath', [$outdir]);
        $this->assertSame($outdir . '/', $result);
    }

    public function testFindOutputPathAlwaysEndsWithASeparator(): void
    {
        $this->setupTest();
        $instance = $this->buildImport();
        foreach (['', dirname(__DIR__) . '/target/tmptest/internals'] as $input) {
            system('mkdir -p ' . dirname(__DIR__) . '/target/tmptest/internals');
            $result = $this->callStringMethod($instance, 'findOutputPath', [$input]);
            $this->assertStringEndsWith('/', $result);
        }
    }

    // -------------------------------------------------------------------------
    // isUnicodeType
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array{0: string, 1: bool}>
     */
    public static function unicodeTypeProvider(): array
    {
        return [
            ['TrueTypeUnicode', true],
            ['cidfont0',        true],
            ['TrueType',        false],
            ['Type1',           false],
            ['Core',            false],
            ['',                false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unicodeTypeProvider')]
    public function testIsUnicodeType(string $type, bool $expected): void
    {
        $instance = $this->buildImport();
        $ref = new \ReflectionMethod($instance, 'isUnicodeType');
        $this->assertSame($expected, $ref->invoke($instance, $type));
    }

    /**
     * Build the minimal fdt that saveFontData needs to emit a TrueType definition file.
     *
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed>
     */
    private function saveableFdt(string $dir, array $over = []): array
    {
        return array_replace([
            'Ascent' => 800,
            'AvgWidth' => 600,
            'CapHeight' => 700,
            'Descent' => -200,
            'Flags' => 32,
            'Leading' => 0,
            'MaxWidth' => 1000,
            'MissingWidth' => 600,
            'StemH' => 0,
            'StemV' => 70,
            'XHeight' => 500,
            'bbox' => '0 -200 1000 800',
            'cbbox' => [],
            'cbboxu' => [],
            'ctg' => 'saveme.ctg.z',
            'ctgdata' => [65 => 36],
            'cw' => [65 => 600],
            'cwu' => [],
            'datafile' => $dir . 'saveme.json',
            'dir' => $dir,
            'diff' => '',
            'enc' => '',
            'encoding_id' => 1,
            'file' => 'saveme.z',
            'italicAngle' => 0,
            'name' => 'saveme',
            'originalsize' => 1234,
            'platform_id' => 3,
            'underlinePosition' => -100,
            'underlineThickness' => 50,
        ], $over);
    }

    /**
     * A processor may downgrade 'TrueTypeUnicode' to 'TrueType', so saveFontData() re-derives
     * the isUnicode flag from the final type.
     *
     * @throws FileException
     */
    public function testSaveFontDataRederivesIsUnicodeFromTheFinalType(): void
    {
        $dir = dirname(__DIR__) . '/target/tmptest/saveisunicode/';
        system('rm -rf ' . escapeshellarg($dir));
        system('mkdir -p ' . escapeshellarg($dir));

        $instance = $this->buildImport();
        $helper = new \Com\Tecnick\File\File(allowedPaths: [rtrim($dir, '/')]);
        $prop = new \ReflectionProperty($instance, 'fileHelper');
        $prop->setValue($instance, $helper);

        // the flag as it stood before the downgrade
        $this->setFdt($instance, $this->saveableFdt($dir, [
            'type' => 'TrueType',
            'isUnicode' => true,
        ]));

        $ref = new \ReflectionMethod($instance, 'saveFontData');
        $ref->invoke($instance);

        $json = (string) file_get_contents($dir . 'saveme.json');
        $this->assertStringContainsString('"type":"TrueType"', $json);
        $this->assertStringContainsString('"isUnicode":false', $json);
        $this->assertStringNotContainsString('"isUnicode":true', $json);

        /** @var mixed $decoded */
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['isUnicode'] ?? null);
    }

    /**
     * @throws FileException
     */
    public function testSaveFontDataKeepsIsUnicodeForTrueTypeUnicode(): void
    {
        $dir = dirname(__DIR__) . '/target/tmptest/saveisunicode2/';
        system('rm -rf ' . escapeshellarg($dir));
        system('mkdir -p ' . escapeshellarg($dir));

        $instance = $this->buildImport();
        $helper = new \Com\Tecnick\File\File(allowedPaths: [rtrim($dir, '/')]);
        $prop = new \ReflectionProperty($instance, 'fileHelper');
        $prop->setValue($instance, $helper);

        // the flag starts out wrong in the other direction: it must be corrected too
        $this->setFdt($instance, $this->saveableFdt($dir, [
            'type' => 'TrueTypeUnicode',
            'isUnicode' => false,
        ]));

        $ref = new \ReflectionMethod($instance, 'saveFontData');
        $ref->invoke($instance);

        $json = (string) file_get_contents($dir . 'saveme.json');
        $this->assertStringContainsString('"isUnicode":true', $json);
    }
}
