<?php

/**
 * Output.php
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

use Com\Tecnick\Pdf\Font\Subset;
use Com\Tecnick\Pdf\Encrypt\Encrypt;
use Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Output
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class Output extends \Com\Tecnick\Pdf\Font\OutFont
{
    /**
     * Array of imported fonts data
     *
     * @var array<string, array{
*        'n': int,
*        'i': int,
*        'key': string,
*        'ifile': string,
*        'family': string,
*        'unicode': bool,
*        'pdfa': bool,
*        'style': string,
*        'fakestyle': bool,
*        'mode': array{
*            'bold': bool,
*            'italic': bool,
*            'underline': bool,
*            'linethrough': bool,
*            'overline': bool,
*        },
*        'type': string,
*        'name': string,
*        'desc':  array{
*            'Flags': int,
*            'FontBBox': string,
*            'ItalicAngle': int,
*            'Ascent': int,
*            'Descent': int,
*            'Leading': int,
*            'CapHeight': int,
*            'XHeight': int,
*            'StemV': int,
*            'StemH': int,
*            'AvgWidth': int,
*            'MaxWidth': int,
*            'MissingWidth': int,
*        },
*        'up': int,
*        'ut': int,
*        'cw':  array<int, int>,
*        'cbbox': array<int, array<int, int>>,
*        'dw': int,
*        'enc': string,
*        'cidinfo': array{
*            'Registry': string,
*            'Ordering': string,
*            'Supplement': int,
*            'uni2cid': array<int, int>,
*        },
*        'file': string,
*        'dir': string,
*        'ctg': string,
*        'diff': string,
*        'diff_n': int,
*        'subset': bool,
*        'subsetchars': array<int, bool>,
*        'compress': bool,
*        'platform_id': int,
*        'encoding_id': int,
*        'originalsize': int,
*        'isUnicode': bool,
*        'length1': int,
*        'length2': bool,
*        'file_n': int,
*    }>
     */
    protected array $fonts;

    /**
     * Array of character subsets for each font file
     *
     * @var array
     */
    protected array $subchars = array();

    /**
     * PDF string block to return containinf the fonts definitions
     *
     * @var string
     */
    protected string $out = '';

    /**
     * Map methods used to process each font type
     *
     * @var array<string, string>
     */
    protected const OUTFONTMAP = array(
        'core'            => 'getCore',
        'cidfont0'        => 'getCid0',
        'type1'           => 'getTrueType',
        'truetype'        => 'getTrueType',
        'truetypeunicode' => 'getTrueTypeUnicode'
    );

    /**
     * Initialize font data
     *
     * @param array<string, array{
*        'n': int,
*        'i': int,
*        'key': string,
*        'ifile': string,
*        'family': string,
*        'unicode': bool,
*        'pdfa': bool,
*        'style': string,
*        'fakestyle': bool,
*        'mode': array{
*            'bold': bool,
*            'italic': bool,
*            'underline': bool,
*            'linethrough': bool,
*            'overline': bool,
*        },
*        'type': string,
*        'name': string,
*        'desc':  array{
*            'Flags': int,
*            'FontBBox': string,
*            'ItalicAngle': int,
*            'Ascent': int,
*            'Descent': int,
*            'Leading': int,
*            'CapHeight': int,
*            'XHeight': int,
*            'StemV': int,
*            'StemH': int,
*            'AvgWidth': int,
*            'MaxWidth': int,
*            'MissingWidth': int,
*        },
*        'up': int,
*        'ut': int,
*        'cw':  array<int, int>,
*        'cbbox': array<int, array<int, int>>,
*        'dw': int,
*        'enc': string,
*        'cidinfo': array{
*            'Registry': string,
*            'Ordering': string,
*            'Supplement': int,
*            'uni2cid': array<int, int>,
*        },
*        'file': string,
*        'dir': string,
*        'ctg': string,
*        'diff': string,
*        'diff_n': int,
*        'subset': bool,
*        'subsetchars': array<int, bool>,
*        'compress': bool,
*        'platform_id': int,
*        'encoding_id': int,
*        'originalsize': int,
*        'isUnicode': bool,
*        'length1': int,
*        'length2': bool,
*        'file_n': int,
*    }>   $fonts Array of imported fonts data
     * @param int     $pon   Current PDF Object Number
     * @param Encrypt $enc   Encrypt object
     */
    public function __construct(array $fonts, int $pon, Encrypt $enc)
    {
        $this->fonts = $fonts;
        $this->pon = $pon;
        $this->enc = $enc;

        $this->out = $this->getEncodingDiffs();
        $this->out .= $this->getFontFiles();
        $this->out .= $this->getFontDefinitions();
    }

    /**
     * Returns current PDF object number
     *
     * @return int
     */
    public function getObjectNumber(): int
    {
        return $this->pon;
    }

    /**
     * Returns the PDF fonts block
     *
     * @return string
     */
    public function getFontsBlock(): string
    {
        return $this->out;
    }

    /**
     * Get the PDF output string for font encoding diffs
     *
     * return string
     */
    protected function getEncodingDiffs(): string
    {
        $out = '';
        $done = array(); // store processed items to avoid duplication
        foreach ($this->fonts as $fkey => $font) {
            if (!empty($font['diff'])) {
                $dkey = md5($font['diff']);
                if (!isset($done[$dkey])) {
                    $out .= (++$this->pon) . ' 0 obj' . "\n"
                        . '<< /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences ['
                        . $font['diff'] . '] >>' . "\n"
                        . 'endobj' . "\n";
                    $done[$dkey] = $this->pon;
                }
                $this->fonts[$fkey]['diff_n'] = $done[$dkey];
            }

            // extract the character subset
            if (!empty($font['file'])) {
                $file_key = md5($font['file']);
                if (empty($this->subchars[$file_key])) {
                    $this->subchars[$file_key] = $font['subsetchars'];
                } else {
                    $this->subchars[$file_key] = array_merge($this->subchars[$file_key], $font['subsetchars']);
                }
            }
        }
        return $out;
    }

    /**
     * Get the PDF output string for font files
     *
     * return string
     */
    protected function getFontFiles(): string
    {
        $out = '';
        $done = array(); // store processed items to avoid duplication
        foreach ($this->fonts as $fkey => $font) {
            if (!empty($font['file'])) {
                $dkey = md5($font['file']);
                if (!isset($done[$dkey])) {
                    $fontfile = $this->getFontFullPath($font['dir'], $font['file']);
                    $font_data = file_get_contents($fontfile);
                    if ($font['subset']) {
                        $font_data = gzuncompress($font_data);
                        $sub = new Subset($font_data, $font, $this->subchars[md5($font['file'])]);
                        $font_data = $sub->getSubsetFont();
                        $font['length1'] = strlen($font_data);
                        $font_data = gzcompress($font_data);
                    }
                    ++$this->pon;
                    $stream = $this->enc->encryptString($font_data, $this->pon);
                    $out .= $this->pon . ' 0 obj' . "\n"
                        . '<<'
                        . ' /Filter /FlateDecode'
                        . ' /Length ' . strlen($stream)
                        . ' /Length1 ' . $font['length1'];
                    if (isset($font['length2'])) {
                        $out .= ' /Length2 ' . $font['length2']
                            . ' /Length3 0';
                    }
                    $out .= ' >>'
                        . ' stream' . "\n"
                        . $stream . "\n"
                        . 'endstream' . "\n"
                        . 'endobj' . "\n";
                    $done[$dkey] = $this->pon;
                }
                $this->fonts[$fkey]['file_n'] = $done[$dkey];
            }
        }
        return $out;
    }

    /**
     * Get the PDF output string for fonts
     *
     * return string
     */
    protected function getFontDefinitions(): string
    {
        $out = '';
        foreach ($this->fonts as $font) {
            if (!isset(self::OUTFONTMAP[strtolower($font['type'])])) {
                throw new FontException('Unsupported font type: ' . $font['type']);
            }
            $method = self::OUTFONTMAP[strtolower($font['type'])];
            $out .= $this->$method($font);
        }
        return $out;
    }
}
