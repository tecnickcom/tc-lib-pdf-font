<?php

/**
 * ImportUtil.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Com\Tecnick\Pdf\Font;

use Com\Tecnick\File\Byte;
use Com\Tecnick\File\Dir;
use Com\Tecnick\File\File;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Import\TrueType;
use Com\Tecnick\Unicode\Data\Encoding;

/**
 * Com\Tecnick\Pdf\Font\ImportUtil
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
abstract class ImportUtil
{
    /**
     * Content of the input font file
     */
    protected string $font = '';

    /**
     * Object used to read font bytes
     */
    protected Byte $fbyte;

    /**
     * Extracted font metrics
     *
     * @var array{
     *        'Ascent': int,
     *        'AvgWidth': float,
     *        'CapHeight': int,
     *        'Descent': int,
     *        'Flags': int,
     *        'Leading': int,
     *        'MaxWidth': int,
     *        'MissingWidth': int,
     *        'StemH': int,
     *        'StemV': int,
     *        'XHeight': int,
     *        'bbox': string,
     *        'ctg': string,
     *        'ctgdata': array<int, int>,
     *        'cw': string,
     *        'datafile': string,
     *        'diff': string,
     *        'dir': string,
     *        'enc': string,
     *        'enc_map': array< int, string>,
     *        'encoding_id': int,
     *        'encrypted': string,
     *        'file': string,
     *        'file_name': string,
     *        'input_file': string,
     *        'isUnicode': bool,
     *        'italicAngle': int,
     *        'lenIV': int,
     *        'linked': bool,
     *        'name': string,
     *        'originalsize': int,
     *        'platform_id': int,
     *        'settype': string,
     *        'size1': int,
     *        'size2': int,
     *        'type': string,
     *        'underlinePosition': int,
     *        'underlineThickness': int,
     *        'weight': string,
     *    }
     */
    protected array $fdt = [
        'Ascent' => 0,
        'AvgWidth' => 0.0,
        'CapHeight' => 0,
        'Descent' => 0,
        'Flags' => 0,
        'Leading' => 0,
        'MaxWidth' => 0,
        'MissingWidth' => 0,
        'StemH' => 0,
        'StemV' => 0,
        'XHeight' => 0,
        'bbox' => '',
        'ctg' => '',
        'ctgdata' => [],
        'cw' => '',
        'datafile' => '',
        'diff' => '',
        'dir' => '',
        'enc' => '',
        'enc_map' => [],
        'encoding_id' => 0,
        'encrypted' => '',
        'file' => '',
        'file_name' => '',
        'input_file' => '',
        'isUnicode' => false,
        'italicAngle' => 0,
        'lenIV' => 0,
        'linked' => false,
        'name' => '',
        'originalsize' => 0,
        'platform_id' => 0,
        'settype' => '',
        'size1' => 0,
        'size2' => 0,
        'type' => '',
        'underlinePosition' => 0,
        'underlineThickness' => 0,
        'weight' => '',
    ];

    /**
     * Make the output font name
     *
     * @param string $font_file Input font file
     */
    protected function makeFontName(string $font_file): string
    {
        $font_path_parts = pathinfo($font_file);
        return str_replace(
            ['bold', 'oblique', 'italic', 'regular'],
            ['b', 'i', 'i', ''],
            preg_replace('/[^a-z0-9_]/', '', strtolower($font_path_parts['filename']))
        );
    }

    /**
     * Find the path where to store the processed font.
     *
     * @param string $output_path    Output path for generated font files (must be writeable by the web server).
     *                               Leave null for default font folder (K_PATH_FONTS).
     */
    protected function findOutputPath(string $output_path = ''): string
    {
        if ($output_path !== '' && is_writable($output_path)) {
            return $output_path;
        }

        if (defined('K_PATH_FONTS') && is_writable(K_PATH_FONTS)) {
            return K_PATH_FONTS;
        }

        $dirobj = new Dir();
        $dir = $dirobj->findParentDir('fonts', __DIR__);
        if ($dir == '/') {
            $dir = sys_get_temp_dir();
        }

        if (! str_ends_with($dir, '/')) {
            $dir .= '/';
        }

        return $dir;
    }

    /**
     * Get the font type
     *
     * @param string $font_type      Font type. Leave empty for autodetect mode.
     */
    protected function getFontType(string $font_type): string
    {
        // autodetect font type
        if ($font_type === '') {
            if (str_starts_with($this->font, 'StartFontMetrics')) {
                // AFM type - we use this type only for the 14 Core fonts
                return 'Core';
            }

            if (str_starts_with($this->font, 'OTTO')) {
                throw new FontException('Unsupported font format: OpenType with CFF data');
            }

            if ($this->fbyte->getULong(0) == 0x10000) {
                return 'TrueTypeUnicode';
            }

            return 'Type1';
        }

        if (str_starts_with($font_type, 'CID0')) {
            return 'cidfont0';
        }

        if (in_array($font_type, ['Core', 'Type1', 'TrueType', 'TrueTypeUnicode'])) {
            return $font_type;
        }

        throw new FontException('unknown or unsupported font type: ' . $font_type);
    }

    /**
     * Get the encoding table
     *
     * @param string $encoding  Name of the encoding table to use. Leave empty for default mode.
     *                          Omit this parameter for TrueType Unicode and symbolic fonts
     *                          like Symbol or ZapfDingBats.
     */
    protected function getEncodingTable(string $encoding = '')
    {
        if ($encoding === '') {
            if (($this->fdt['type'] == 'Type1') && (($this->fdt['Flags'] & 4) == 0)) {
                return 'cp1252';
            }

            return '';
        }

        return preg_replace('/[^A-Za-z0-9_\-]/', '', $encoding);
    }

    /**
     * If required, get differences between the reference encoding (cp1252) and the current encoding
     */
    protected function getEncodingDiff(): string
    {
        $diff = '';
        if (
            (($this->fdt['type'] == 'TrueType') || ($this->fdt['type'] == 'Type1'))
            && (! empty($this->fdt['enc'])
            && ($this->fdt['enc'] != 'cp1252')
            && isset(Encoding::MAP[$this->fdt['enc']]))
        ) {
            // build differences from reference encoding
            $enc_ref = Encoding::MAP['cp1252'];
            $enc_target = Encoding::MAP[$this->fdt['enc']];
            $last = 0;
            for ($idx = 32; $idx <= 255; ++$idx) {
                if ($enc_target[$idx] != $enc_ref[$idx]) {
                    if ($idx != $last + 1) {
                        $diff .= $idx . ' ';
                    }

                    $last = $idx;
                    $diff .= '/' . $enc_target[$idx] . ' ';
                }
            }
        }

        return $diff;
    }

    /**
     * Update the CIDToGIDMap string with a new value
     *
     * @param string $map CIDToGIDMap.
     * @param int    $cid CID value.
     * @param int    $gid GID value.
     */
    protected function updateCIDtoGIDmap(string $map, int $cid, int $gid): string
    {
        if (($cid >= 0) && ($cid <= 0xFFFF) && ($gid >= 0)) {
            if ($gid > 0xFFFF) {
                $gid -= 0x10000;
            }

            $map[($cid * 2)] = chr($gid >> 8);
            $map[(($cid * 2) + 1)] = chr($gid & 0xFF);
        }

        return $map;
    }
}
