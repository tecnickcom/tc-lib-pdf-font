<?php

/**
 * Stack.php
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

use Com\Tecnick\Pdf\Font\Font;
use Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Stack
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class Stack extends \Com\Tecnick\Pdf\Font\Buffer
{
    /**
     * Default font size in points
     */
    const DEFAULT_SIZE = 10;

    /**
     * Array (stack) containing fonts in order of insertion.
     * The last item is the current font.
     *
     * @var array<int, array{
    *        'key': string,
    *        'style': string,
    *        'size': float,
    *        'spacing': float,
    *        'stretching': float,
    *    }>
     */
    protected array $stack = array();

    /**
     * Current font index
     *
     * @var int
     */
    protected int $index = -1;

    /**
     * Array containing font metrics for each fontkey-size combination.
     *
     * @var array<string, array{
    *     'outraw': string,
    *     'out': string,
    *     'key': string,
    *     'type': string,
    *     'size': float,
    *     'spacing': float,
    *     'stretching': float,
    *     'usize': float,
    *     'cratio': float2,
    *     'up': float,
    *     'ut': float,
    *     'dw': float,
    *     'ascent': float,
    *     'descent': float,
    *     'height': float,
    *     'midpoint': float,
    *     'capheight': float,
    *     'xheight': float,
    *     'avgwidth': float,
    *     'maxwidth': float,
    *     'missingwidth': float,
    *     'cw': array<int, float>,
    *     'cbbox': array<int, array<int, float>>,
    *     'fbbox': array<int, float>,
    * }>
     */
    protected array $metric = array();

    /**
     * Insert a font into the stack
     *
     * The definition file (and the font file itself when embedding) must be present either in the current directory
     * or in the one indicated by K_PATH_FONTS if the constant is defined.
     *
     * @param int    $objnum     Current PDF object number
     * @param string $font       Font family, or comma separated list of font families
     *                           If it is a standard family name, it will override the corresponding font.
     * @param string $style      Font style.
     *                           Possible values are (case insensitive):
     *                             regular (default)
     *                             B: bold
     *                             I: italic
     *                             U: underline
     *                             D: strikeout (linethrough)
     *                             O: overline
     * @param ?int     $size       Font size in points (set to null to inherit the last font size).
     * @param ?float  $spacing    Extra spacing between characters.
     * @param ?float  $stretching Horizontal character stretching ratio.
     * @param string $ifile      The font definition file (or empty for autodetect).
     *                           By default, the name is built from the family and style, in lower case with no spaces.
     * @param ?bool   $subset     If true embedd only a subset of the font
     *                           (stores only the information related to the used characters);
     *                           If false embedd full font;
     *                           This option is valid only for TrueTypeUnicode fonts and it is disabled for PDF/A.
     *                           If you want to enable users to modify the document, set this parameter to false.
     *                           If you subset the font, the person who receives your PDF would need to have
     *                           your same font in order to make changes to your PDF.
     *                           The file size of the PDF would also be smaller because you are embedding only a subset.
     *                           Set this to null to use the default value.
     *                           NOTE: This option is computational and memory intensive.
     *
     * @return array{
    *     'outraw': string,
    *     'out': string,
    *     'key': string,
    *     'type': string,
    *     'size': float,
    *     'spacing': float,
    *     'stretching': float,
    *     'usize': float,
    *     'cratio': float2,
    *     'up': float,
    *     'ut': float,
    *     'dw': float,
    *     'ascent': float,
    *     'descent': float,
    *     'height': float,
    *     'midpoint': float,
    *     'capheight': float,
    *     'xheight': float,
    *     'avgwidth': float,
    *     'maxwidth': float,
    *     'missingwidth': float,
    *     'cw': array<int, float>,
    *     'cbbox': array<int, array<int, float>>,
    *     'fbbox': array<int, float>,
    * } Font data
     *
     * @throws FontException in case of error
     */
    public function insert(
        int &$objnum,
        string $font,
        string $style = '',
        ?int $size = null,
        ?float $spacing = null,
        ?float $stretching = null,
        string $ifile = '',
        ?bool $subset = null
    ) {
        if ($subset === null) {
            $subset = $this->subset;
        }
        $size = $this->getInputSize($size);
        $spacing = $this->getInputSpacing($spacing);
        $stretching = $this->getInputStretching($stretching);

        // try to load the corresponding imported font
        $err = null;
        $keys = $this->getNormalizedFontKeys($font);
        $fontkey = '';
        foreach ($keys as $fkey) {
            try {
                $fontkey = $this->add($objnum, $fkey, $style, $ifile, $subset);
                $err = null;
                break;
            } catch (FontException $exc) {
                $err = $exc;
            }
        }
        if ($err !== null) {
            throw new FontException($err->getMessage());
        }

        // add this font in the stack
        $data = $this->getFont($fontkey);

        $this->stack[++$this->index] = array(
            'key'        => $fontkey,
            'style'      => $data['style'],
            'size'       => $size,
            'spacing'    => $spacing,
            'stretching' => $stretching,
        );

        return $this->getFontMetric($this->stack[$this->index]);
    }

    /**
     * Returns the current font data array
     *
     * @return array{
    *     'outraw': string,
    *     'out': string,
    *     'key': string,
    *     'type': string,
    *     'size': float,
    *     'spacing': float,
    *     'stretching': float,
    *     'usize': float,
    *     'cratio': float2,
    *     'up': float,
    *     'ut': float,
    *     'dw': float,
    *     'ascent': float,
    *     'descent': float,
    *     'height': float,
    *     'midpoint': float,
    *     'capheight': float,
    *     'xheight': float,
    *     'avgwidth': float,
    *     'maxwidth': float,
    *     'missingwidth': float,
    *     'cw': array<int, float>,
    *     'cbbox': array<int, array<int, float>>,
    *     'fbbox': array<int, float>,
    * }
     */
    public function getCurrentFont(): array
    {
        return $this->getFontMetric($this->stack[$this->index]);
    }

    /**
     * Returns the current font type (i.e.: Core, TrueType, TrueTypeUnicode, Type1).
     *
     * @return string
     */
    public function getCurrentFontType(): string
    {
        return $this->getFont($this->stack[$this->index]['key'])['type'];
    }

    /**
     * Returns the PDF code to use the current font.
     *
     * @return string
     */
    public function getOutCurrentFont(): string
    {
        return $this->getFontMetric($this->stack[$this->index])['out'];
    }

    /**
     * Returns true if the current font type is Core, TrueType or Type1.
     *
     * @return bool
     */
    public function isCurrentByteFont(): bool
    {
        $type = $this->getCurrentFontType();
        return !(($type == 'Core') || ($type == 'TrueType') || ($type == 'Type1'));
    }

    /**
     * Returns true if the current font type is TrueTypeUnicode or cidfont0.
     *
     * @return bool
     */
    public function isCurrentUnicodeFont(): bool
    {
        $type = $this->getCurrentFontType();
        return !(($type == 'TrueTypeUnicode') || ($type == 'cidfont0'));
    }

    /**
     * Remove and return the last inserted font
     *
     * @return array{
    *     'outraw': string,
    *     'out': string,
    *     'key': string,
    *     'type': string,
    *     'size': float,
    *     'spacing': float,
    *     'stretching': float,
    *     'usize': float,
    *     'cratio': float2,
    *     'up': float,
    *     'ut': float,
    *     'dw': float,
    *     'ascent': float,
    *     'descent': float,
    *     'height': float,
    *     'midpoint': float,
    *     'capheight': float,
    *     'xheight': float,
    *     'avgwidth': float,
    *     'maxwidth': float,
    *     'missingwidth': float,
    *     'cw': array<int, float>,
    *     'cbbox': array<int, array<int, float>>,
    *     'fbbox': array<int, float>,
    * }
     */
    public function popLastFont(): array
    {
        if ($this->index < 0) {
            throw new FontException('The font stack is empty');
        }
        $font = array_pop($this->stack);
        --$this->index;
        return $this->getFontMetric($font);
    }

    /**
     * Replace missing characters with selected substitutions
     *
     * @param array<int, int> $uniarr Array of character codepoints.
     * @param array<int, int> $subs   Array of possible character substitutions.
     *                      The key is the character to check (integer value),
     *                      the value is an array of possible substitutes.
     *
     * @return array<int, int> Array of character codepoints.
     */
    public function replaceMissingChars(array $uniarr, array $subs = array()): array
    {
        $font = $this->getFontMetric($this->stack[$this->index]);
        foreach ($uniarr as $pos => $uni) {
            if (isset($font['cw'][$uni]) || !isset($subs[$uni])) {
                continue;
            }
            foreach ($subs[$uni] as $alt) {
                if (isset($font['cw'][$alt])) {
                    $uniarr[$pos] = $alt;
                    break;
                }
            }
        }
        return $uniarr;
    }

    /**
     * Returns true if the specified unicode value is defined in the current font
     *
     * @param int $ord Unicode character value to convert
     *
     * @return bool
     */
    public function isCharDefined(int $ord): bool
    {
        $font = $this->getFontMetric($this->stack[$this->index]);
        return isset($font['cw'][$ord]);
    }

    /**
     * Returns the width of the specified character
     *
     * @param int   $ord    Unicode character value.
     *
     * @return float
     */
    public function getCharWidth(int $ord): float
    {
        if ($ord == 173) {
            // SHY character is not printed, as it is used for text hyphenation
            return 0;
        }
        $font = $this->getFontMetric($this->stack[$this->index]);
        if (isset($font['cw'][$ord])) {
            return $font['cw'][$ord];
        }
        return $font['dw'];
    }

    /**
     * Returns the lenght of the string specified using an array of codepoints.
     *
     * @param array<int, int> $uniarr Array of character codepoints.
     *
     * @return float
     */
    public function getOrdArrWidth(array $uniarr): float
    {
        return $this->getOrdArrDims($uniarr)['totwidth'];
    }

    /**
     * Returns various dimensions of the string specified using an array of codepoints.
     *
     * @param array<int, int> $uniarr Array of character codepoints.
     *
     * @return array{
     *     'chars': int, 
     *     'spaces': int, 
     *     'totwidth': int, 
     *     'totspacewidth': int,
     * }
     */
    public function getOrdArrDims(array $uniarr): array
    {
        $chars = count($uniarr); // total number of chars
        $spaces = 0; // total number of spaces
        $totwidth = 0; // total string width
        $totspacewidth = 0; // total space width
        $spw = $this->getCharWidth(32); // width of a single space
        foreach ($uniarr as $ord) {
            $totwidth += $this->getCharWidth($ord);
            if ($ord == 32) {
                ++$spaces;
                $totspacewidth += $spw;
            }
        }
        $fact = ($this->stack[$this->index]['spacing'] * $this->stack[$this->index]['stretching']);
        $totwidth += ($fact * ($chars - 1));
        $totspacewidth += ($fact * ($spaces - 1));
        return array(
            'chars' => $chars,
            'spaces' => $spaces,
            'totwidth' => $totwidth,
            'totspacewidth' => $totspacewidth
        );
    }

    /**
     * Returns the glyph bounding box of the specified character in the current font in user units.
     *
     * @param int $ord Unicode character value.
     *
     * @return array<int> (xMin, yMin, xMax, yMax)
     */
    public function getCharBBox(int $ord): array
    {
        $font = $this->getFontMetric($this->stack[$this->index]);
        if (isset($font['cbbox'][$ord])) {
            return $font['cbbox'][$ord];
        }
        return array(0, 0, 0, 0); // glyph without outline
    }

    /**
     * Replace a char if it is defined on the current font.
     *
     * @param int $oldchar Integer code (unicode) of the character to replace.
     * @param int $newchar Integer code (unicode) of the new character.
     *
     * @return int the replaced char or the old char in case the new char i not defined
     */
    public function replaceChar(int $oldchar, int $newchar): int
    {
        if ($this->isCharDefined($newchar)) {
            // add the new char on the subset list
            $this->addSubsetChar($this->stack[$this->index]['key'], $newchar);
            // return the new character
            return $newchar;
        }
        // return the old char
        return $oldchar;
    }

    /**
     * Returns the font metrics associated to the input key.
     *
     * @param array{
    *        'key': string,
    *        'style': string,
    *        'size': float,
    *        'spacing': float,
    *        'stretching': float,
    *    } $font Stack item
     *
     * @return array{
    *     'outraw': string,
    *     'out': string,
    *     'key': string,
    *     'type': string,
    *     'size': float,
    *     'spacing': float,
    *     'stretching': float,
    *     'usize': float,
    *     'cratio': float2,
    *     'up': float,
    *     'ut': float,
    *     'dw': float,
    *     'ascent': float,
    *     'descent': float,
    *     'height': float,
    *     'midpoint': float,
    *     'capheight': float,
    *     'xheight': float,
    *     'avgwidth': float,
    *     'maxwidth': float,
    *     'missingwidth': float,
    *     'cw': array<int, float>,
    *     'cbbox': array<int, array<int, float>>,
    *     'fbbox': array<int, float>,
    * }
     */
    protected function getFontMetric(array $font): array
    {
        $mkey = md5(serialize($font));
        if (!empty($this->metric[$mkey])) {
            return $this->metric[$mkey];
        }
        $size = ((float) $font['size']);
        $usize = ($size / $this->kunit);
        $cratio = ($size / 1000);
        $wratio = ($cratio * $font['stretching']); // horizontal ratio
        $data = $this->getFont($font['key']);
        $outfont = sprintf('/F%d %F Tf', $data['i'], $font['size']); // PDF output string
        // add this font in the stack wit metrics in internal units
        $this->metric[$mkey] = array(
            'outraw'       => $outfont,
            'out'          => sprintf('BT ' . $outfont . ' ET' . "\r"), // PDF output string
            'key'          => $font['key'],
            'type'         => $data['type'],
            'size'         => $size,   // size in points
            'spacing'      => $font['spacing'],
            'stretching'   => $font['stretching'],
            'usize'        => $usize,  // size in user units
            'cratio'       => $cratio, // conversion ratio
            'up'           => ($data['up'] * $cratio),
            'ut'           => ($data['ut'] * $cratio),
            'dw'           => ($data['dw'] * $cratio * $font['stretching']),
            'ascent'       => ($data['desc']['Ascent'] * $cratio),
            'descent'      => ($data['desc']['Descent'] * $cratio),
            'height'       => (($data['desc']['Ascent'] - $data['desc']['Descent']) * $cratio),
            'midpoint'     => (($data['desc']['Ascent'] + $data['desc']['Descent']) * $cratio / 2),
            'capheight'    => ($data['desc']['CapHeight'] * $cratio),
            'xheight'      => ($data['desc']['XHeight'] * $cratio),
            'avgwidth'     => ($data['desc']['AvgWidth'] * $cratio * $font['stretching']),
            'maxwidth'     => ($data['desc']['MaxWidth'] * $cratio * $font['stretching']),
            'missingwidth' => ($data['desc']['MissingWidth'] * $cratio * $font['stretching']),
            'cw'           => array(),
            'cbbox'        => array(),
        );
        $tbox = explode(' ', substr($data['desc']['FontBBox'], 1, -1));
        $this->metric[$mkey]['fbbox'] = array(
            ((float)$tbox[0] * $wratio), // left
            ((float)$tbox[1] * $cratio), // bottom
            ((float)$tbox[2] * $wratio), // right
            ((float)$tbox[3] * $cratio), // top
        );
        //left, bottom, right, and top edges
        foreach ($data['cw'] as $chr => $width) {
            $this->metric[$mkey]['cw'][$chr] = ($width * $wratio);
        }
        foreach ($data['cbbox'] as $chr => $val) {
            $this->metric[$mkey]['cbbox'][$chr] = array(
                ($val[0] * $wratio), // left
                ($val[1] * $cratio), // bottom
                ($val[2] * $wratio), // right
                ($val[3] * $cratio), // top
            );
        }

        return $this->metric[$mkey];
    }

    /**
     * Normalize the input size
     * 
     * @param ?int $size Font size in points (set to null to inherit the last font size).
     *
     * return float
     */
    protected function getInputSize(?int $size = null): float
    {
        if (($size === null) || ($size < 0)) {
            if ($this->index >= 0) {
                // inherit the size of the last inserted font
                return $this->stack[$this->index]['size'];
            } else {
                return self::DEFAULT_SIZE;
            }
        }
        return max(0, (float) $size);
    }

    /**
     * Normalize the input spacing
     *
     * @param ?float  $spacing  Extra spacing between characters.
     *
     * return float
     */
    protected function getInputSpacing(?float $spacing = null): float
    {
        if ($spacing === null) {
            if ($this->index >= 0) {
                // inherit the size of the last inserted font
                return $this->stack[$this->index]['spacing'];
            } else {
                return 0;
            }
        }
        return ((float) $spacing);
    }

    /**
     * Normalize the input stretching
     *
     * @param ?float  $stretching Horizontal character stretching ratio.
     *
     * return float
     */
    protected function getInputStretching(?float $stretching = null): float
    {
        if ($stretching === null) {
            if ($this->index >= 0) {
                // inherit the size of the last inserted font
                return $this->stack[$this->index]['stretching'];
            } else {
                return 1;
            }
        }
        return ((float) $stretching);
    }

    /**
     * Return normalized font keys
     *
     * @param string $fontfamily Property string containing comma-separated font family names
     *
     * @return array<string>
     */
    protected function getNormalizedFontKeys(string $fontfamily): array
    {
        $keys = array();
        // remove spaces and symbols
        $fontfamily = preg_replace('/[^a-z0-9_\,]/', '', strtolower($fontfamily));
        // extract all font names
        $fontslist = preg_split('/[,]/', $fontfamily);
        // replacement patterns
        $pattern = array('/^serif|^cursive|^fantasy|^timesnewroman/', '/^sansserif/', '/^monospace/');
        $replacement = array('times', 'helvetica', 'courier');
        // find first valid font name
        foreach ($fontslist as $font) {
            // replace font variations
            $font = preg_replace('/regular$/', '', $font);
            $font = preg_replace('/italic$/', 'I', $font);
            $font = preg_replace('/oblique$/', 'I', $font);
            $font = preg_replace('/bold([I]?)$/', 'B\\1', $font);
            // replace common family names and core fonts
            $keys[] = preg_replace($pattern, $replacement, $font);
        }
        return $keys;
    }
}
