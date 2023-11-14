<?php

/**
 * Font.php
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

use Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Font
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class Font extends \Com\Tecnick\Pdf\Font\Load
{
    /**
     * Load an imported font
     *
     * The definition file (and the font file itself when embedding) must be present either in the current directory
     * or in the one indicated by K_PATH_FONTS if the constant is defined.
     *
     * @param string $font   Font family.
     *                       If it is a standard family name, it will override the corresponding font.
     * @param string $style  Font style.
     *                       Possible values are (case insensitive):
     *                          regular (default)
     *                          B: bold
     *                          I: italic
     *                          U: underline
     *                          D: strikeout (linethrough)
     *                          O: overline
     * @param string $ifile  The font definition file (or empty for autodetect).
     *                       By default, the name is built from the family and style, in lower case with no spaces.
     * @param bool   $subset If true embedd only a subset of the font
     *                       (stores only the information related to the used characters);
     *                       If false embedd full font;
     *                       This option is valid only for TrueTypeUnicode fonts and it is disabled for PDF/A.
     *                       If you want to enable users to modify the document, set this parameter to false.
     *                       If you subset the font, the person who receives your PDF would need to have
     *                       your same font in order to make changes to your PDF.
     *                       The file size of the PDF would also be smaller because you are embedding only a subset.
     * @param bool $unicode  True if we are in Unicode mode, False otherwhise.
     * @param bool $pdfa     True if we are in PDF/A mode.
     * @param bool $compress Set to false to disable stream compression.
     *
     * @throws FontException in case of error
     */
    public function __construct(
        string $font,
        string $style = '',
        string $ifile = '',
        bool $subset = false,
        bool $unicode = true,
        bool $pdfa = false,
        bool $compress = true
    ) {
        if (empty($font)) {
            throw new FontException('empty font family name');
        }
        $this->data['ifile'] = $ifile;
        $this->data['family'] = $font;
        $this->data['unicode'] = (bool) $unicode;
        $this->data['pdfa'] = (bool) $pdfa;
        $this->data['compress'] = (bool) $compress;
        $this->data['subset'] = $subset;
        $this->data['subsetchars'] = array_fill(0, 255, true);

        // generate the font key and set styles
        $this->setStyle($style);
    }

    /**
     * Get the font key
     *
     * @return string
     */
    public function getFontkey(): string
    {
        return $this->data['key'];
    }

    /**
     * Get the font data
     *
     * @return array
     */
    public function getFontData(): array
    {
        return $this->data;
    }

    /**
     * Set style and normalize the font name
     *
     * @param string $style Style
     */
    protected function setStyle(string $style): void
    {
        $style = strtoupper($style);
        if (substr($this->data['family'], -1) == 'I') {
            $style .= 'I';
            $this->data['family'] = substr($this->data['family'], 0, -1);
        }
        if (substr($this->data['family'], -1) == 'B') {
            $style .= 'B';
            $this->data['family'] = substr($this->data['family'], 0, -1);
        }
        // normalize family name
        $this->data['family'] = strtolower($this->data['family']);
        if ((!$this->data['unicode']) && ($this->data['family'] == 'arial')) {
            $this->data['family'] = 'helvetica';
        }
        if (($this->data['family'] == 'symbol') || ($this->data['family'] == 'zapfdingbats')) {
            $style = '';
        }
        if ($this->data['pdfa'] && (isset(Core::FONT[$this->data['family']]))) {
            // core fonts must be embedded in PDF/A
            $this->data['family'] = 'pdfa' . $this->data['family'];
        }
        $this->setStyleMode($style);
    }

    /**
     * Set style mode properties
     *
     * @param string $style Style
     */
    protected function setStyleMode(string $style): void
    {
        $suffix = '';
        if (strpos($style, 'B') !== false) {
            $this->data['mode']['bold'] = true;
            $suffix .= 'B';
        }
        if (strpos($style, 'I') !== false) {
            $this->data['mode']['italic'] = true;
            $suffix .= 'I';
        }
        $this->data['style'] = $suffix;
        if (strpos($style, 'U') !== false) {
            $this->data['mode']['underline'] = true;
            $this->data['style'] .= 'U';
        }
        if (strpos($style, 'D') !== false) {
            $this->data['mode']['linethrough'] = true;
            $this->data['style'] .= 'D';
        }
        if (strpos($style, 'O') !== false) {
            $this->data['mode']['overline'] = true;
            $this->data['style'] .= 'O';
        }
        $this->data['key'] = $this->data['family'] . $suffix;
    }
}
