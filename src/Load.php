<?php

/**
 * Load.php
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

use Com\Tecnick\File\Dir;
use Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Load
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
abstract class Load
{
    /**
     * Valid Font types
     *
     * @var array<string, bool> Font types
     */
    protected const FONTTYPES = [
        'Core' => true,
        'TrueType' => true,
        'TrueTypeUnicode' => true,
        'Type1' => true,
        'cidfont0' => true,
    ];

    /**
     * Font data
     *
     * @var array{
     *        'cbbox': array<int, array<int, int>>,
     *        'cidinfo': array{
     *            'Ordering': string,
     *            'Registry': string,
     *            'Supplement': int,
     *            'uni2cid': array<int, int>,
     *        },
     *        'compress': bool,
     *        'ctg': string,
     *        'cw':  array<int, int>,
     *        'desc':  array{
     *            'Ascent': int,
     *            'AvgWidth': int,
     *            'CapHeight': int,
     *            'Descent': int,
     *            'Flags': int,
     *            'FontBBox': string,
     *            'ItalicAngle': int,
     *            'Leading': int,
     *            'MaxWidth': int,
     *            'MissingWidth': int,
     *            'StemH': int,
     *            'StemV': int,
     *            'XHeight': int,
     *        },
     *        'diff': string,
     *        'diff_n': int,
     *        'dir': string,
     *        'dw': int,
     *        'enc': string,
     *        'encoding_id': int,
     *        'fakestyle': bool,
     *        'family': string,
     *        'file': string,
     *        'file_n': int,
     *        'i': int,
     *        'ifile': string,
     *        'isUnicode': bool,
     *        'key': string,
     *        'length1': int,
     *        'length2': bool|int,
     *        'mode': array{
     *            'bold': bool,
     *            'italic': bool,
     *            'linethrough': bool,
     *            'overline': bool,
     *            'underline': bool,
     *        },
     *        'n': int,
     *        'name': string,
     *        'originalsize': int,
     *        'pdfa': bool,
     *        'platform_id': int,
     *        'style': string,
     *        'subset': bool,
     *        'subsetchars': array<int, bool>,
     *        'type': string,
     *        'unicode': bool,
     *        'up': int,
     *        'ut': int,
     *    }
     */
    protected array $data = [
        'cbbox' => [],
        'cidinfo' => [
            'Ordering' => 'Identity',
            'Registry' => 'Adobe',
            'Supplement' => 0,
            'uni2cid' => [],
        ],
        'compress' => false,
        'ctg' => '',
        'cw' => [],
        'desc' => [
            'Ascent' => 0,
            'AvgWidth' => 0,
            'CapHeight' => 0,
            'Descent' => 0,
            'Flags' => 0,
            'FontBBox' => '',
            'ItalicAngle' => 0,
            'Leading' => 0,
            'MaxWidth' => 0,
            'MissingWidth' => 0,
            'StemH' => 0,
            'StemV' => 0,
            'XHeight' => 0,
        ],
        'diff' => '',
        'diff_n' => 0,
        'dir' => '',
        'dw' => 0,
        'enc' => '',
        'encoding_id' => 0,
        'fakestyle' => false,
        'family' => '',
        'file' => '',
        'file_n' => 0,
        'i' => 0,
        'ifile' => '',
        'isUnicode' => false,
        'key' => '',
        'length1' => 0,
        'length2' => false,
        'mode' => [
            'bold' => false,
            'italic' => false,
            'linethrough' => false,
            'overline' => false,
            'underline' => false,
        ],
        'n' => 0,
        'name' => '',
        'originalsize' => 0,
        'pdfa' => false,
        'platform_id' => 0,
        'style' => '',
        'subset' => false,
        'subsetchars' => [],
        'type' => '',
        'unicode' => false,
        'up' => 0,
        'ut' => 0,
    ];

    /**
     * Load the font data
     *
     * @throws FontException in case of error
     */
    public function load(): void
    {
        $fontInfo = $this->getFontInfo();
        $this->data = array_merge($this->data, $fontInfo);
        $this->checkType();
        $this->setName();
        $this->setDefaultWidth();
        if ($this->data['fakestyle']) {
            $this->setArtificialStyles();
        }

        $this->setFileData();
    }

    /**
     * Load the font data
     *
     * @return array{
     *        'cbbox': array<int, array<int, int>>,
     *        'cidinfo': array{
     *            'Ordering': string,
     *            'Registry': string,
     *            'Supplement': int,
     *            'uni2cid': array<int, int>,
     *        },
     *        'compress': bool,
     *        'ctg': string,
     *        'cw':  array<int, int>,
     *        'desc':  array{
     *            'Ascent': int,
     *            'AvgWidth': int,
     *            'CapHeight': int,
     *            'Descent': int,
     *            'Flags': int,
     *            'FontBBox': string,
     *            'ItalicAngle': int,
     *            'Leading': int,
     *            'MaxWidth': int,
     *            'MissingWidth': int,
     *            'StemH': int,
     *            'StemV': int,
     *            'XHeight': int,
     *        },
     *        'diff': string,
     *        'diff_n': int,
     *        'dir': string,
     *        'dw': int,
     *        'enc': string,
     *        'encoding_id': int,
     *        'fakestyle': bool,
     *        'family': string,
     *        'file': string,
     *        'file_n': int,
     *        'i': int,
     *        'ifile': string,
     *        'isUnicode': bool,
     *        'key': string,
     *        'length1': int,
     *        'length2': bool|int,
     *        'mode': array{
     *            'bold': bool,
     *            'italic': bool,
     *            'linethrough': bool,
     *            'overline': bool,
     *            'underline': bool,
     *        },
     *        'n': int,
     *        'name': string,
     *        'originalsize': int,
     *        'pdfa': bool,
     *        'platform_id': int,
     *        'style': string,
     *        'subset': bool,
     *        'subsetchars': array<int, bool>,
     *        'type': string,
     *        'unicode': bool,
     *        'up': int,
     *        'ut': int,
     *    } Font data
     *
     * @throws FontException in case of error
     */
    protected function getFontInfo(): array
    {
        $this->findFontFile();

        // read the font definition file
        if (! @is_readable($this->data['ifile'])) {
            throw new FontException('unable to read file: ' . $this->data['ifile']);
        }

        $fdt = @file_get_contents($this->data['ifile']);
        $fdt = @json_decode($fdt, true);
        if ($fdt === null) {
            throw new FontException('JSON decoding error [' . json_last_error() . ']');
        }

        if (empty($fdt['type']) || empty($fdt['cw'])) {
            throw new FontException('fhe font definition file has a bad format: ' . $this->data['ifile']);
        }

        return $fdt;
    }

    /**
     * Returns a list of font directories
     *
     * @return array<string> Font directories
     */
    protected function findFontDirectories(): array
    {
        $dir = new Dir();
        $dirs = [''];
        if (defined('K_PATH_FONTS')) {
            $dirs[] = K_PATH_FONTS;
            $dirs = array_merge($dirs, glob(K_PATH_FONTS . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR));
        }

        $parent_font_dir = $dir->findParentDir('fonts', __DIR__);
        if ($parent_font_dir !== '') {
            $dirs[] = $parent_font_dir;
            $dirs = array_merge($dirs, glob($parent_font_dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR));
        }

        return array_unique($dirs);
    }

    /**
     * Load the font data
     *
     * @throws FontException in case of error
     */
    protected function findFontFile(): void
    {
        if (! empty($this->data['ifile'])) {
            return;
        }

        $this->data['ifile'] = strtolower($this->data['key']) . '.json';

        // directories where to search for the font definition file
        $dirs = $this->findFontDirectories();

        // find font definition file names
        $files = array_unique(
            [strtolower($this->data['key']) . '.json', strtolower($this->data['family']) . '.json']
        );

        foreach ($files as $file) {
            foreach ($dirs as $dir) {
                if (@is_readable($dir . DIRECTORY_SEPARATOR . $file)) {
                    $this->data['ifile'] = $dir . DIRECTORY_SEPARATOR . $file;
                    $this->data['dir'] = $dir;
                    break 2;
                }
            }

            // we haven't found the version with style variations
            $this->data['fakestyle'] = true;
        }
    }

    protected function setDefaultWidth(): void
    {
        if (! empty($this->data['dw'])) {
            return;
        }

        if (isset($this->data['desc']['MissingWidth']) && ($this->data['desc']['MissingWidth'] > 0)) {
            $this->data['dw'] = $this->data['desc']['MissingWidth'];
        } elseif (! empty($this->data['cw'][32])) {
            $this->data['dw'] = $this->data['cw'][32];
        } else {
            $this->data['dw'] = 600;
        }
    }

    /**
     * Check Font Type
     */
    protected function checkType(): void
    {
        if (isset(self::FONTTYPES[$this->data['type']])) {
            return;
        }

        throw new FontException('Unknow font type: ' . $this->data['type']);
    }

    protected function setName(): void
    {
        if ($this->data['type'] == 'Core') {
            $this->data['name'] = Core::FONT[$this->data['key']];
            $this->data['subset'] = false;
        } elseif (($this->data['type'] == 'Type1') || ($this->data['type'] == 'TrueType')) {
            $this->data['subset'] = false;
        } elseif ($this->data['type'] == 'TrueTypeUnicode') {
            $this->data['enc'] = 'Identity-H';
        } elseif (($this->data['type'] == 'cidfont0') && ($this->data['pdfa'])) {
            throw new FontException('CID0 fonts are not supported, all fonts must be embedded in PDF/A mode!');
        }

        if (empty($this->data['name'])) {
            $this->data['name'] = $this->data['key'];
        }
    }

    /**
     * Set artificial styles if the font variation file is missing
     */
    protected function setArtificialStyles(): void
    {
        // artificial bold
        if ($this->data['mode']['bold']) {
            $this->data['name'] .= 'Bold';
            $this->data['desc']['StemV'] = empty($this->data['desc']['StemV'])
                ? 123 : round($this->data['desc']['StemV'] * 1.75);
        }

        // artificial italic
        if ($this->data['mode']['italic']) {
            $this->data['name'] .= 'Italic';
            if (! empty($this->data['desc']['ItalicAngle'])) {
                $this->data['desc']['ItalicAngle'] -= 11;
            } else {
                $this->data['desc']['ItalicAngle'] = -11;
            }

            if (! empty($this->data['desc']['Flags'])) {
                $this->data['desc']['Flags'] |= 64; //bit 7
            } else {
                $this->data['desc']['Flags'] = 64;
            }
        }
    }

    public function setFileData(): void
    {
        if (empty($this->data['file'])) {
            return;
        }

        if (str_contains($this->data['type'], 'TrueType')) {
            $this->data['length1'] = $this->data['originalsize'];
            $this->data['length2'] = false;
        } elseif ($this->data['type'] != 'Core') {
            $this->data['length1'] = $this->data['size1'];
            $this->data['length2'] = $this->data['size2'];
        }
    }
}
