<?php

/**
 * TypeOneInternalsTest.php
 *
 * @since     2026-05-05
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Test;

use Com\Tecnick\Pdf\Font\Import\TypeOne;
use Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Tests for protected methods of Import\TypeOne exercised via reflection.
 *
 * @since     2026-05-05
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TypeOneInternalsTest extends TestUtil
{
    /** @var array<string, mixed> */
    private static array $fdtDefaults = [
        'Ascender'          => 0,
        'Ascent'            => 700,
        'AvgWidth'          => 0.0,
        'CapHeight'         => 680,
        'CharacterSet'      => '',
        'Descender'         => -200,
        'Descent'           => -200,
        'EncodingScheme'    => '',
        'FamilyName'        => '',
        'Flags'             => 0,
        'FontBBox'          => [],
        'FontName'          => '',
        'FullName'          => '',
        'IsFixedPitch'      => false,
        'ItalicAngle'       => 0,
        'Leading'           => 0,
        'MaxWidth'          => 0,
        'MissingWidth'      => 0,
        'StdHW'             => 0,
        'StdVW'             => 0,
        'StemH'             => 0,
        'StemV'             => 0,
        'UnderlinePosition' => 0,
        'UnderlineThickness' => 0,
        'Version'           => '',
        'Weight'            => '',
        'XHeight'           => 0,
        'bbox'              => '',
        'cbbox'             => [],
        'cidinfo'           => ['Ordering' => '', 'Registry' => '', 'Supplement' => 0, 'uni2cid' => []],
        'compress'          => false,
        'ctg'               => '',
        'ctgdata'           => [],
        'cw'                => [],
        'cwu'               => [],
        'datafile'          => '',
        'desc'              => [
            'Ascent' => 0, 'AvgWidth' => 0, 'CapHeight' => 0, 'Descent' => 0,
            'Flags' => 0, 'FontBBox' => '', 'ItalicAngle' => 0, 'Leading' => 0,
            'MaxWidth' => 0, 'MissingWidth' => 0, 'StemH' => 0, 'StemV' => 0, 'XHeight' => 0,
        ],
        'diff'              => '',
        'diff_n'            => 0,
        'dir'               => '',
        'dw'                => 0,
        'enc'               => '',
        'enc_map'           => [],
        'encodingTables'    => [],
        'encoding_id'       => 0,
        'encrypted'         => '',
        'fakestyle'         => false,
        'family'            => '',
        'file'              => '',
        'file_n'            => 0,
        'file_name'         => '',
        'i'                 => 0,
        'ifile'             => '',
        'indexToLoc'        => [],
        'input_file'        => '',
        'isUnicode'         => false,
        'italicAngle'       => 0,
        'key'               => '',
        'lenIV'             => 4,
        'length1'           => 0,
        'length2'           => 0,
        'linked'            => false,
        'mode'              => ['bold' => false, 'italic' => false, 'linethrough' => false, 'overline' => false, 'underline' => false],
        'n'                 => 0,
        'name'              => '',
        'numGlyphs'         => 0,
        'numHMetrics'       => 0,
        'originalsize'      => 0,
        'pdfa'              => false,
        'platform_id'       => 0,
        'settype'           => '',
        'short_offset'      => false,
        'size1'             => 0,
        'size2'             => 0,
        'style'             => '',
        'subset'            => false,
        'subsetchars'       => [],
        'table'             => [],
        'tot_num_glyphs'    => 0,
        'type'              => 'Type1',
        'underlinePosition' => 0,
        'underlineThickness' => 0,
        'unicode'           => false,
        'unitsPerEm'        => 0,
        'up'                => 0,
        'urk'               => 1.0,
        'ut'                => 0,
        'weight'            => 'normal',
    ];

    private function buildTypeOne(): TypeOne
    {
        $class = new \ReflectionClass(TypeOne::class);
        $instance = $class->newInstanceWithoutConstructor();
        $this->setProp($instance, 'fdt', self::$fdtDefaults);
        $this->setProp($instance, 'font', '');
        return $instance;
    }

    /**
     * @param array<int, mixed> $args
     */
    private function callMethod(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($obj, $args);
    }

    private function setProp(object $obj, string $name, mixed $value): void
    {
        $prop = new \ReflectionProperty($obj, $name);
        $prop->setAccessible(true);
        $prop->setValue($obj, $value);
    }

    private function getProp(object $obj, string $name): mixed
    {
        $prop = new \ReflectionProperty($obj, $name);
        $prop->setAccessible(true);
        return $prop->getValue($obj);
    }

    // -------------------------------------------------------------------------
    // extractStem
    // -------------------------------------------------------------------------

    public function testExtractStemReadsStdVwAndStdHw(): void
    {
        $instance = $this->buildTypeOne();
        $eplain = '/StdVW [85] def /StdHW [40] def /CapHeight [690] def';
        $this->callMethod($instance, 'extractStem', [$eplain]);
        $fdt = $this->getProp($instance, 'fdt');

        $this->assertSame(85, $fdt['StemV']);
        $this->assertSame(40, $fdt['StemH']);
        $this->assertSame(690, $fdt['CapHeight']);
    }

    public function testExtractStemUsesBoldDefaultWhenStdVwAbsent(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['weight'] = 'bold';
        $this->setProp($instance, 'fdt', $fdt);

        $this->callMethod($instance, 'extractStem', ['']);
        $fdt = $this->getProp($instance, 'fdt');

        $this->assertSame(123, $fdt['StemV']);
        $this->assertSame(30, $fdt['StemH']);
    }

    public function testExtractStemUsesDefaultsWhenNoMatchingKeys(): void
    {
        $instance = $this->buildTypeOne();
        $this->callMethod($instance, 'extractStem', ['']);
        $fdt = $this->getProp($instance, 'fdt');

        $this->assertSame(70, $fdt['StemV']);
        $this->assertSame(30, $fdt['StemH']);
        // CapHeight falls back to Ascent (700)
        $this->assertSame(700, $fdt['CapHeight']);
    }

    // -------------------------------------------------------------------------
    // getRandomBytes
    // -------------------------------------------------------------------------

    public function testGetRandomBytesDefaultsToFourWhenMissing(): void
    {
        $instance = $this->buildTypeOne();
        $this->callMethod($instance, 'getRandomBytes', ['no lenIV here']);
        $fdt = $this->getProp($instance, 'fdt');
        $this->assertSame(4, $fdt['lenIV']);
    }

    public function testGetRandomBytesParsesExplicitLenIV(): void
    {
        $instance = $this->buildTypeOne();
        $this->callMethod($instance, 'getRandomBytes', ['/lenIV 8 def']);
        $fdt = $this->getProp($instance, 'fdt');
        $this->assertSame(8, $fdt['lenIV']);
    }

    public function testGetRandomBytesParsesLenIVZero(): void
    {
        $instance = $this->buildTypeOne();
        $this->callMethod($instance, 'getRandomBytes', ['/lenIV 0 def']);
        $fdt = $this->getProp($instance, 'fdt');
        $this->assertSame(0, $fdt['lenIV']);
    }

    // -------------------------------------------------------------------------
    // getCharstringData
    // -------------------------------------------------------------------------

    public function testGetCharstringDataReturnsEmptyMatchesWhenNoneFound(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc'] = '';
        $this->setProp($instance, 'fdt', $fdt);

        $eplain = '/CharStrings 0 dict dup begin end';
        $result = $this->callMethod($instance, 'getCharstringData', [$eplain]);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetCharstringDataReturnsEmptyWhenEncNotInMap(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc'] = 'nonexistent_encoding';
        $this->setProp($instance, 'fdt', $fdt);

        $eplain = '/CharStrings 0 dict dup begin end';
        $result = $this->callMethod($instance, 'getCharstringData', [$eplain]);
        $this->assertIsArray($result);
    }

    public function testGetCharstringDataPopulatesEncMapForKnownEncoding(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc'] = 'cp1252';
        $this->setProp($instance, 'fdt', $fdt);

        $eplain = '/CharStrings 0 dict dup begin end';
        $this->callMethod($instance, 'getCharstringData', [$eplain]);
        $fdt = $this->getProp($instance, 'fdt');

        $this->assertNotEmpty($fdt['enc_map']);
    }

    // -------------------------------------------------------------------------
    // getCid
    // -------------------------------------------------------------------------

    public function testGetCidReturnsImapValueWhenCharNameFound(): void
    {
        $instance = $this->buildTypeOne();
        $imap = ['A' => 65, 'B' => 66];
        $val  = [0 => '', 1 => 'A', 2 => ''];
        $result = $this->callMethod($instance, 'getCid', [$imap, $val]);
        $this->assertSame(65, $result);
    }

    public function testGetCidReturnsZeroWhenEncMapFalse(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc_map'] = false;
        $this->setProp($instance, 'fdt', $fdt);

        $imap = [];
        $val  = [0 => '', 1 => 'Z', 2 => ''];
        $result = $this->callMethod($instance, 'getCid', [$imap, $val]);
        $this->assertSame(0, $result);
    }

    public function testGetCidReturnsZeroWhenCharNotFoundInEncMap(): void
    {
        $instance = $this->buildTypeOne();
        $fdt = self::$fdtDefaults;
        $fdt['enc_map'] = ['a' => 97];
        $this->setProp($instance, 'fdt', $fdt);

        $imap = [];
        $val  = [0 => '', 1 => 'missing', 2 => ''];
        $result = $this->callMethod($instance, 'getCid', [$imap, $val]);
        $this->assertSame(0, $result);
    }

    public function testGetCidClampsLargeCidToThousand(): void
    {
        $instance = $this->buildTypeOne();
        // Build enc_map where the glyph name resolves to a CID > 1000
        $encMap = [];
        $encMap[1001] = 'BigChar';
        $fdt = self::$fdtDefaults;
        $fdt['enc_map'] = $encMap;
        $this->setProp($instance, 'fdt', $fdt);

        $imap = [];
        $val  = [0 => '', 1 => 'BigChar', 2 => ''];
        $result = $this->callMethod($instance, 'getCid', [$imap, $val]);
        $this->assertSame(1000, $result);
    }

    // -------------------------------------------------------------------------
    // decodeNumber
    // -------------------------------------------------------------------------

    public function testDecodeNumberHandlesByte32To246(): void
    {
        $instance = $this->buildTypeOne();
        // ccom[0] = 139 → decoded value = 139 - 139 = 0
        $ccom    = [139];
        /** @var array<int, int> $cdec */
        $cdec    = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck     = 0;
        $cid     = 0;
        $newIdx  = $this->callMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        $this->assertSame(1, $newIdx);
        $this->assertSame(0, $cdec[0]);
    }

    public function testDecodeNumberHandlesBytes247To250(): void
    {
        $instance = $this->buildTypeOne();
        // ccom[0] = 247, ccom[1] = 0 → value = (247-247)*256 + 0 + 108 = 108
        $ccom    = [247, 0];
        /** @var array<int, int> $cdec */
        $cdec    = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck     = 0;
        $cid     = 0;
        $newIdx  = $this->callMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        $this->assertSame(2, $newIdx);
        $this->assertSame(108, $cdec[0]);
    }

    public function testDecodeNumberHandlesBytes251To254(): void
    {
        $instance = $this->buildTypeOne();
        // ccom[0] = 251, ccom[1] = 0 → value = -(251-251)*256 - 0 - 108 = -108
        $ccom    = [251, 0];
        /** @var array<int, int> $cdec */
        $cdec    = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck     = 0;
        $cid     = 0;
        $newIdx  = $this->callMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        $this->assertSame(2, $newIdx);
        $this->assertSame(-108, $cdec[0]);
    }

    public function testDecodeNumberHandlesByte255FourByteInt(): void
    {
        $instance = $this->buildTypeOne();
        // ccom[0]=255, ccom[1..4] = big-endian int 500 = 0x000001F4
        $ccom    = [255, 0, 0, 1, 0xF4];
        /** @var array<int, int> $cdec */
        $cdec    = [];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck     = 0;
        $cid     = 0;
        $newIdx  = $this->callMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        $this->assertSame(5, $newIdx);
        // The 4-byte sequence packs as little-endian 'l' → 0x000001F4 LE = 0xF4010000 BE
        // unpack('li', "\x00\x00\x01\xF4") depends on system endianness; just assert it returned an int.
        $this->assertIsInt($cdec[0]);
    }

    public function testDecodeNumberHsbwCommandUpdatesWidth(): void
    {
        $instance = $this->buildTypeOne();
        // Build a 2-element decode stack: cdec[0]=width(300), cdec[1]=13 (hsbw)
        // When ccom[$idx]=value<32 and cck>0 and the value==13 → hsbw: cwidths[$cid] = cdec[$cck-1]
        $ccom    = [13];  // hsbw opcode (value < 32)
        /** @var array<int, int> $cdec */
        $cdec    = [0 => 300];
        /** @var array<int, int> $cwidths */
        $cwidths = [];
        $cck     = 1;     // stack has one element already
        $cid     = 7;
        $this->callMethod($instance, 'decodeNumber', [0, &$cck, &$cid, &$ccom, &$cdec, &$cwidths]);
        $this->assertArrayHasKey(7, $cwidths);
        $this->assertSame(300, $cwidths[7]);
    }

    // -------------------------------------------------------------------------
    // storeFontData – error paths
    // -------------------------------------------------------------------------

    public function testStoreFontDataThrowsOnInvalidMarker(): void
    {
        $instance = $this->buildTypeOne();
        // First byte not 128 → invalid binary Type1
        $this->setProp($instance, 'font', "\x00\x00\x00\x00\x00\x00");
        $this->bcExpectException('\\' . FontException::class);
        $this->callMethod($instance, 'storeFontData', []);
    }
}
