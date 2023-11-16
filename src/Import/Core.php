<?php

/**
 * Core.php
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

namespace Com\Tecnick\Pdf\Font\Import;

use Com\Tecnick\File\File;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Unicode\Data\Encoding;

/**
 * Com\Tecnick\Pdf\Font\Import\Core
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class Core
{
    /**
     * Map property names to the correct key name.
     *
     * @var array<string, string>
     */
    protected const PROPERTYMAP = [
        'FullName' => 'name',
        'UnderlinePosition' => 'underlinePosition',
        'UnderlineThickness' => 'underlineThickness',
        'ItalicAngle' => 'italicAngle',
        'Ascender' => 'Ascent',
        'Descender' => 'Descent',
        'StdVW' => 'StemV',
        'StdHW' => 'StemH',
    ];

    /**
     * @param string $font    Content of the input font file
     * @param array{
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
     *    }  $fdt Extracted font metrics
     *
     * @throws FontException in case of error
     */
    public function __construct(
        /**
         * Content of the input font file
         */
        protected string $font, /**
         * Extracted font metrics
         */
        protected array $fdt
    ) {
        $this->process();
    }

    /**
     * Get all the extracted font metrics
     *
     * @return array{
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
    public function getFontMetrics(): array
    {
        return $this->fdt;
    }

    protected function setFlags(): void
    {
        if (($this->fdt['FontName'] == 'Symbol') || ($this->fdt['FontName'] == 'ZapfDingbats')) {
            $this->fdt['Flags'] |= 4;
        } else {
            $this->fdt['Flags'] |= 32;
        }

        if ($this->fdt['IsFixedPitch']) {
            $this->fdt['Flags'] |= 1;
        }

        if ($this->fdt['ItalicAngle'] != 0) {
            $this->fdt['Flags'] |= 64;
        }
    }

    /**
     * Set Char widths
     *
     * @param array<int, int> $cwidths Extracted widths
     */
    protected function setCharWidths(array $cwidths): void
    {
        $this->fdt['MissingWidth'] = 600;
        if (! empty($cwidths[32])) {
            $this->fdt['MissingWidth'] = $cwidths[32];
        }

        $this->fdt['MaxWidth'] = $this->fdt['MissingWidth'];
        $this->fdt['AvgWidth'] = 0;
        $this->fdt['cw'] = '';
        for ($cid = 0; $cid <= 255; ++$cid) {
            if (isset($cwidths[$cid])) {
                if ($cwidths[$cid] > $this->fdt['MaxWidth']) {
                    $this->fdt['MaxWidth'] = $cwidths[$cid];
                }

                $this->fdt['AvgWidth'] += $cwidths[$cid];
                $this->fdt['cw'] .= ',"' . $cid . '":' . $cwidths[$cid];
            } else {
                $this->fdt['cw'] .= ',"' . $cid . '":' . $this->fdt['MissingWidth'];
            }
        }

        $this->fdt['AvgWidth'] = round($this->fdt['AvgWidth'] / count($cwidths));
    }

    /**
     * Extract Metrics
     */
    protected function extractMetrics(): void
    {
        $cwd = [];
        $this->fdt['cbbox'] = '';
        $lines = explode("\n", str_replace("\r", '', $this->font));
        // process each row
        foreach ($lines as $line) {
            $col = explode(' ', rtrim($line));
            if (count($col) > 1) {
                $this->processMetricRow($col, $cwd);
            }
        }

        $this->fdt['Leading'] = 0;
        $this->setCharWidths($cwd);
    }

    /**
     * Extract Metrics
     *
     * @param array<int, string> $col Array containing row elements to process
     * @param array<int, int> $cwd Array contianing cid widths
     */
    protected function processMetricRow(array $col, array &$cwd): void
    {
        if (($col[0] == 'C') && (($cid = (int) $col[1]) >= 0)) {
            // character metrics
            $cwd[$cid] = (int) $col[4];
            if (! empty($col[14])) {
                //cbbox
                $this->fdt['cbbox'] .= ',"' . $cid
                . '":[' . $col[10] . ',' . $col[11] . ',' . $col[12] . ',' . $col[13] . ']';
            }
        } elseif (
            in_array(
                $col[0],
                [
                    'FontName',
                    'FullName',
                    'FamilyName',
                    'Weight',
                    'CharacterSet',
                    'Version',
                    'EncodingScheme',
                ]
            )
        ) {
            $this->fdt[$col[0]] = $col[1];
        } elseif (
            in_array(
                $col[0],
                [
                    'ItalicAngle',
                    'UnderlinePosition',
                    'UnderlineThickness',
                    'CapHeight',
                    'XHeight',
                    'Ascender',
                    'Descender',
                    'StdHW',
                    'StdVW',
                ]
            )
        ) {
            $this->fdt[$col[0]] = (int) $col[1];
        } elseif ($col[0] == 'IsFixedPitch') {
            $this->fdt[$col[0]] = ($col[1] == 'true');
        } elseif ($col[0] == 'FontBBox') {
            $this->fdt[$col[0]] = [(int) $col[1], (int) $col[2], (int) $col[3], (int) $col[4]];
        }
    }

    /**
     * Map values to the correct key name
     */
    protected function remapValues(): void
    {
        foreach (self::PROPERTYMAP as $old => $new) {
            $this->fdt[$new] = $this->fdt[$old];
        }

        $this->fdt['name'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $this->fdt['name']);
        $this->fdt['bbox'] = implode(' ', $this->fdt['FontBBox']);

        if (empty($this->fdt['XHeight'])) {
            $this->fdt['XHeight'] = 0;
        }
    }

    protected function setMissingValues(): void
    {
        $this->fdt['Descender'] = $this->fdt['FontBBox'][1];

        $this->fdt['Ascender'] = $this->fdt['FontBBox'][3];

        if (! isset($this->fdt['CapHeight'])) {
            $this->fdt['CapHeight'] = $this->fdt['Ascender'];
        }
    }

    /**
     * Process Core font
     */
    protected function process(): void
    {
        $this->extractMetrics();
        $this->setFlags();
        $this->setMissingValues();
        $this->remapValues();
    }
}
