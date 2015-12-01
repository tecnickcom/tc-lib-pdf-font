<?php
/**
 * Buffer.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Com\Tecnick\Pdf\Font;

use \Com\Tecnick\Pdf\Font\Font;
use \Com\Tecnick\Pdf\Font\Output;
use \Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Buffer
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class Buffer
{
    /**
     * Array containing all fonts data
     *
     * @var array
     */
    protected $font = array();
    
    /**
     * Font counter
     *
     * @var int
     */
    protected $numfonts = 0;

    /**
     * Array containing encoding differences
     *
     * @var array
     */
    protected $encdiff = array();
    
    /**
     * Index for Encoding differences
     *
     * @var int
     */
    protected $numdiffs = 0;

    /**
     * Array containing font definitions grouped by file
     *
     * @var array
     */
    protected $file = array();

    /**
     * returns the fonts buffer
     *
     * @return array
     */
    public function getFonts()
    {
        return $this->font;
    }

    /**
     * returns the fonts buffer
     *
     * @return array
     */
    public function getEncDiffs()
    {
        return $this->encdiff;
    }

    /**
     * Get font by key
     *
     * @param string $key Font key
     *
     * @return array|bool Returns the fonts array of palse in case of missing font.
     *
     * @throws FontException in case of error
     */
    public function getFont($key)
    {
        if (!isset($this->font[$key])) {
            throw new FontException('The font '.$key.' has not been loaded');
        }
        return $this->font[$key];
    }

    /**
     * Set font sub-key value
     *
     * @param int   $key    The font key
     * @param int   $subkey Font sub-key
     * @param mixed $data   The data to set
     */
    public function setFontSubKey($key, $subkey, $data)
    {
        if (!isset($this->font[$key])) {
            $this->font[$key] = array();
        }
        $this->font[$key][$subkey] = $data;
    }

    /**
     * Add a new font to the fonts buffer
     *
     * The definition file (and the font file itself when embedding) must be present either in the current directory
     * or in the one indicated by K_PATH_FONTS if the constant is defined.
     *
     * @param int    $objnum Current PDF object number
     * @param string $font   Font family.
     *                       If it is a standard family name, it will override the corresponding font.
     * @param string $style  Font style.
     *                       Possible values are (case insensitive):
     *                          regular (default)
     *                          B: bold
     *                          I: italic
     *                          BI: bold italic
     * @param string $ifile  The font definition file.
     *                       By default, the name is built from the family and style, in lower case with no spaces.
     * @param bool   $subset If true embedd only a subset of the font
     *                       (stores only the information related to the used characters);
     *                       If false embedd full font;
     *                       This option is valid only for TrueTypeUnicode fonts and it is disable for PDF/A.
     *                       If you want to enable users to modify the document, set this parameter to false.
     *                       If you subset the font, the person who receives your PDF would need to have
     *                       your same font in order to make changes to your PDF.
     *                       The file size of the PDF would also be smaller because you are embedding only a subset.
     * @param bool $unicode  True if we are in Unicode mode, False otherwhise.
     * @param bool $pdfa     True if we are in PDF/A mode.
     *
     * @return string Font key
     *
     * @throws FontException in case of error
     */
    public function add(&$objnum, $font, $style = '', $ifile = '', $subset = false, $unicode = true, $pdfa = false)
    {
        $fobj = new Font($font, $style, $ifile, $subset, $unicode, $pdfa);
        $key = $fobj->getFontkey();
        if (isset($this->font[$key])) {
            return $key;
        }

        $fobj->load();
        $this->font[$key] = $fobj->getFontData();

        if (!empty($this->font[$key]['file'])) {
            $file = $this->font[$key]['file'];
            if (!isset($this->file[$file])) {
                $this->file[$file] = array('keys' => array());
            }
            if (!in_array($key, $this->file[$file]['keys'])) {
                $this->file[$file]['keys'][] = $key;
            }
            $this->file[$file]['dir'] = $this->font[$key]['dir'];
            $this->file[$file]['length1'] = $this->font[$key]['length1'];
            $this->file[$file]['length2'] = $this->font[$key]['length2'];
            if (!isset($this->file[$file]['subset'])) {
                $this->file[$file]['subset'] = true;
            }
            $this->file[$file]['subset'] = ($this->file[$file]['subset'] && $this->font[$key]['subset']);
        }

        $diff = $this->font[$key]['diff'];
        if (!empty($diff)) {
            $diffid = array_search($diff, $this->encdiff);
            if ($diffid === false) {
                $diffid = ++$this->numdiffs;
                $this->encdiff[$diffid] = $diff;
            }
            $this->font[$key]['diffid'] = $diffid;
        }

        $this->font[$key]['i'] = $this->numfonts++;
        $this->font[$key]['n'] = ++$objnum;

        return $key;
    }
}
