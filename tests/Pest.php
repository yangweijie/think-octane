<?php

declare(strict_types=1);

use yangweijie\thinkOctane\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeValidPid', function () {
    return $this->toBeInt()->toBeGreaterThan(0);
});

expect()->extend('toBeValidMemorySize', function () {
    return $this->toBeString()->toMatch('/^\d+(\.\d+)?\s+(B|KB|MB|GB)$/');
});

expect()->extend('toBeValidServerName', function () {
    return $this->toBeIn(['swoole', 'workerman', 'reactphp']);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * 创建测试用的临时目录
 */
function createTempDir(): string
{
    $tempDir = sys_get_temp_dir() . '/octane_test_' . uniqid();
    mkdir($tempDir, 0755, true);
    return $tempDir;
}

/**
 * 递归删除目录
 */
function removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? removeDir($path) : unlink($path);
    }
    rmdir($dir);
}

/**
 * 模拟HTTP请求数据
 */
function mockHttpRequest(array $data = []): array
{
    return array_merge([
        'method' => 'GET',
        'uri' => '/',
        'headers' => ['Host' => 'localhost'],
        'get' => [],
        'post' => [],
        'files' => [],
        'cookie' => [],
    ], $data);
}
