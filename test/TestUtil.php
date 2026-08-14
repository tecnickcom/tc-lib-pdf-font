<?php

/**
 * TestUtil.php
 *
 * @since     2020-12-19
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2015-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Test utilities
 *
 * @since     2020-12-19
 * @category  Library
 * @package   PdfFont
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2015-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @preserveGlobalState         disabled
 * @runTestsInSeparateProcesses
 */
class TestUtil extends TestCase
{
    protected function setupTest(): void
    {
        if (!\defined('K_PATH_FONTS')) {
            \define('K_PATH_FONTS', \dirname(__DIR__) . '/target/tmptest/');
        }

        $fontPath = (string) \constant('K_PATH_FONTS');
        // Removed with PHP rather than by shelling out to 'rm -rf': the path does not have
        // to survive a trip through a shell, no POSIX utility is required, and the output
        // of the command does not end up in the middle of the test report.
        self::removeDirectory($fontPath);
        \mkdir($fontPath, 0o755, true);
    }

    /**
     * Remove a directory and everything below it, if it exists.
     */
    protected static function removeDirectory(string $path): void
    {
        if (\is_link($path) || \is_file($path)) {
            \unlink($path);
            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        $entries = \scandir($path);
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            self::removeDirectory($path . DIRECTORY_SEPARATOR . $entry);
        }

        \rmdir($path);
    }

    protected function getFontPath(): string
    {
        if (\defined('K_PATH_FONTS')) {
            return (string) \constant('K_PATH_FONTS');
        }

        return '';
    }

    public function bcAssertEqualsWithDelta(
        mixed $expected,
        mixed $actual,
        float $delta = 0.01,
        string $message = '',
    ): void {
        parent::assertEqualsWithDelta($expected, $actual, $delta, $message);
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    public function bcExpectException(string $exception): void
    {
        parent::expectException($exception);
    }

    /**
     * Assert that the callback throws $exception and that the message mentions $needle.
     *
     * Written as an explicit catch rather than expectExceptionMessage(), which PHPUnit 13
     * deprecates, and which would also let the assertions after the call be skipped.
     *
     * @param class-string<\Throwable> $exception
     */
    public function assertThrowsMessage(string $exception, string $needle, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $thrown) {
            $this->assertInstanceOf($exception, $thrown);
            $this->assertStringContainsString($needle, $thrown->getMessage());
            return;
        }

        $this->fail($exception . ' mentioning "' . $needle . '" was not thrown.');
    }

    /**
     * Decode a font definition file into an array of members.
     *
     * Several tests read back what an import wrote, and every one of them has to state
     * that the file is valid JSON and that a member holds the type it asserts on; the
     * helpers below say it once.
     *
     * @return array<string, mixed>
     */
    protected function decodeDefinition(string $path): array
    {
        $raw = \file_get_contents($path);
        $this->assertIsString($raw, 'unable to read the definition file: ' . $path);

        /** @var mixed $decoded */
        $decoded = \json_decode($raw, true);
        $this->assertIsArray($decoded, 'the definition file must be valid JSON: ' . $path);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Returns an integer member of an array, failing when it is absent or of another type.
     *
     * @param array<array-key, mixed> $data
     */
    protected function intMember(array $data, string|int $key): int
    {
        $this->assertArrayHasKey($key, $data);
        /** @var mixed $value */
        $value = $data[$key] ?? null;
        $this->assertIsInt($value, 'member ' . $key . ' must be an integer');

        return $value;
    }

    /**
     * Returns a string member of an array, failing when it is absent or of another type.
     *
     * @param array<array-key, mixed> $data
     */
    protected function stringMember(array $data, string|int $key): string
    {
        $this->assertArrayHasKey($key, $data);
        /** @var mixed $value */
        $value = $data[$key] ?? null;
        $this->assertIsString($value, 'member ' . $key . ' must be a string');

        return $value;
    }

    /**
     * Returns an array member of an array, failing when it is absent or of another type.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    protected function arrayMember(array $data, string|int $key): array
    {
        $this->assertArrayHasKey($key, $data);
        /** @var mixed $value */
        $value = $data[$key] ?? null;
        $this->assertIsArray($value, 'member ' . $key . ' must be an array');

        return $value;
    }

    /**
     * Returns the integer values of an array, keyed as they are.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<int, int>
     */
    protected function intMap(array $data): array
    {
        $map = [];
        /** @var mixed $value */
        foreach ($data as $key => $value) {
            $this->assertIsInt($value, 'entry ' . $key . ' must be an integer');
            $map[(int) $key] = $value;
        }

        return $map;
    }
}
