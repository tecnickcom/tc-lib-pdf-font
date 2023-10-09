<?php

/**
 * TestUtil.php
 *
 * @since       2020-12-19
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2021 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-color software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Web Color class test
 *
 * @since      2020-12-19
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2021 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TestUtil extends TestCase
{
    protected $preserveGlobalState = false;
    protected $runTestSepProcess = true;

    protected function setupTest()
    {
        if (!defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', dirname(__DIR__) . '/target/tmptest/');
        }
        system('rm -rf ' . K_PATH_FONTS . ' && mkdir -p ' . K_PATH_FONTS);
    }

    public function bcAssertEqualsWithDelta($expected, $actual, $delta = 0.01, $message = '')
    {
        if (\is_callable([self::class, 'assertEqualsWithDelta'])) {
            return parent::assertEqualsWithDelta($expected, $actual, $delta, $message);
        }
        return $this->assertEquals($expected, $actual, $message, $delta);
    }

    public function bcExpectException($exception)
    {
        if (\is_callable([self::class, 'expectException'])) {
            return parent::expectException($exception);
        }
        return parent::setExpectedException($exception);
    }
}
