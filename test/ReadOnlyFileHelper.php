<?php

/**
 * ReadOnlyFileHelper.php
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

use Com\Tecnick\File\File as ObjFile;

/**
 * File helper handing out a handle that cannot be written to.
 *
 * A stream that refuses the data is how a full disk, an exceeded quota or a failing device
 * surfaces to fwrite().
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class ReadOnlyFileHelper extends ObjFile
{
    #[\Override]
    public function fopenLocal(string $file, string $mode): mixed
    {
        unset($mode);

        // opened for reading, so every write on it fails with a warning, the way a full
        // disk or a failing device reports it
        return \fopen($file, 'rb');
    }
}
