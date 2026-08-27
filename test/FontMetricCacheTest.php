<?php

/**
 * FontMetricCacheTest.php
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

use Com\Tecnick\File\Exception as FileException;
use Com\Tecnick\Pdf\Font\Exception as FontException;
use Com\Tecnick\Pdf\Font\Import;
use Com\Tecnick\Pdf\Font\Stack;

/**
 * The cache of scaled font metrics is bounded.
 *
 * Every entry holds the glyph width and bounding box maps of the font scaled to one size,
 * and the least recently used entries beyond the bound are dropped.
 *
 * @since     2026-08-14
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 */
class FontMetricCacheTest extends TestUtil
{
    private const MIRROR = '/util/vendor/tecnickcom/tc-font-mirror/';

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     */
    private function helveticaStack(): Stack
    {
        $this->setupTest();
        new Import(\dirname(__DIR__) . self::MIRROR . 'core/Helvetica.afm');

        return new Stack(1);
    }

    /**
     * Returns the keys of the metric cache, from the least to the most recently used.
     *
     * @return array<int, string>
     *
     * @throws \ReflectionException
     */
    private function getCacheKeys(Stack $stack): array
    {
        /** @var array<array-key, mixed> $value */
        $value = (new \ReflectionProperty(Stack::class, 'metric'))->getValue($stack);

        $keys = [];
        foreach (\array_keys($value) as $key) {
            $keys[] = (string) $key;
        }

        return $keys;
    }

    /**
     * @throws \ReflectionException
     */
    private function getCacheSize(): int
    {
        /** @var int $size */
        $size = (new \ReflectionClass(Stack::class))->getConstant('METRIC_CACHE_SIZE');
        $this->assertIsInt($size);
        $this->assertGreaterThan(1, $size);

        return $size;
    }

    /**
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     * @throws \ReflectionException
     */
    public function testTheCacheStopsGrowingAtItsBound(): void
    {
        $stack = $this->helveticaStack();
        $bound = $this->getCacheSize();

        $objnum = 1;
        for ($step = 0; $step < ($bound * 3); ++$step) {
            $stack->insert($objnum, 'helvetica', '', 6.0 + ($step * 0.25), 0, 1);
            $this->assertLessThanOrEqual($bound, \count($this->getCacheKeys($stack)));
        }

        $this->assertCount($bound, $this->getCacheKeys($stack), 'the bound is actually reached');
    }

    /**
     * The entries that survive are the ones the document is still using.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     * @throws \ReflectionException
     */
    public function testTheLeastRecentlyUsedEntryIsTheOneDropped(): void
    {
        $stack = $this->helveticaStack();
        $bound = $this->getCacheSize();

        $objnum = 1;
        for ($step = 0; $step < $bound; ++$step) {
            $stack->insert($objnum, 'helvetica', '', 6.0 + $step, 0, 1);
        }

        $keys = $this->getCacheKeys($stack);
        $this->assertCount($bound, $keys);
        $oldest = $keys[0] ?? '';
        $secondOldest = $keys[1] ?? '';

        // touching the oldest entry moves it back to the recent end
        $stack->insert($objnum, 'helvetica', '', 6.0, 0, 1);
        // one more distinct size then evicts the entry that is now the oldest
        $stack->insert($objnum, 'helvetica', '', 6.0 + $bound, 0, 1);

        $surviving = $this->getCacheKeys($stack);
        $this->assertCount($bound, $surviving);
        $this->assertContains($oldest, $surviving, 'the entry used again is kept');
        $this->assertNotContains($secondOldest, $surviving, 'the one left behind is dropped');
    }

    /**
     * An evicted metric is recomputed, so nothing a caller reads depends on the cache.
     *
     * @throws FileException
     * @throws FontException
     * @throws \RangeException
     * @throws \ReflectionException
     */
    public function testAnEvictedMetricIsRecomputedIdentically(): void
    {
        $stack = $this->helveticaStack();
        $bound = $this->getCacheSize();

        $objnum = 1;
        $first = $stack->insert($objnum, 'helvetica', '', 12, 0, 1);

        for ($step = 0; $step < ($bound + 1); ++$step) {
            $stack->insert($objnum, 'helvetica', '', 20.0 + $step, 0, 1);
        }

        $this->assertCount($bound, $this->getCacheKeys($stack));

        $again = $stack->insert($objnum, 'helvetica', '', 12, 0, 1);
        // only the stack position differs, and it is not part of the cached value
        $first['idx'] = 0;
        $again['idx'] = 0;
        $this->assertEquals($first, $again);
    }
}
