<?php

declare(strict_types=1);

use yangweijie\thinkOctane\Support\DebugHelper;

it('can detect debug mode', function () {
    // 确保调试模式启用
    if (!defined('APP_DEBUG')) {
        define('APP_DEBUG', true);
    }
    $_ENV['APP_DEBUG'] = true;

    $isDebugMode = DebugHelper::isDebugMode();

    expect($isDebugMode)->toBeBool();

    // 在测试环境中，调试模式应该是启用的
    expect($isDebugMode)->toBeTrue();
});

it('can detect debug packages', function () {
    $hasDebugPackages = DebugHelper::hasDebugPackages();
    
    expect($hasDebugPackages)->toBeBool();
    
    // 应该检测到 think-trace 包
    expect($hasDebugPackages)->toBeTrue();
});

it('can detect think-trace', function () {
    $hasThinkTrace = DebugHelper::hasThinkTrace();
    
    expect($hasThinkTrace)->toBeBool();
    
    // 应该检测到 think-trace
    expect($hasThinkTrace)->toBeTrue();
});

it('should preserve debug output in debug mode', function () {
    $shouldPreserve = DebugHelper::shouldPreserveDebugOutput();
    
    expect($shouldPreserve)->toBeBool();
    
    // 在调试模式下应该保留输出
    expect($shouldPreserve)->toBeTrue();
});

it('can get debug services list', function () {
    $debugServices = DebugHelper::getDebugServices();
    
    expect($debugServices)->toBeArray();
    
    // 在调试模式下应该包含调试相关服务
    expect($debugServices)->toContain('log');
    expect($debugServices)->toContain('trace');
    expect($debugServices)->toContain('debug');
    expect($debugServices)->toContain('middleware');
});

it('should clear output buffer based on debug mode', function () {
    $shouldClear = DebugHelper::shouldClearOutputBuffer();
    
    expect($shouldClear)->toBeBool();
    
    // 在调试模式下不应该清理输出缓冲区
    expect($shouldClear)->toBeFalse();
});

it('can safely clean output buffer', function () {
    // 创建一些输出缓冲区
    ob_start();
    echo "test output";
    
    $initialLevel = ob_get_level();
    
    // 安全清理
    DebugHelper::safeCleanOutputBuffer();
    
    // 在调试模式下，输出缓冲区应该被保留
    expect(ob_get_level())->toBe($initialLevel);
    
    // 清理测试缓冲区
    ob_end_clean();
});

it('can get comprehensive debug info', function () {
    $debugInfo = DebugHelper::getDebugInfo();
    
    expect($debugInfo)->toBeArray();
    expect($debugInfo)->toHaveKey('debug_mode');
    expect($debugInfo)->toHaveKey('has_think_trace');
    expect($debugInfo)->toHaveKey('has_debug_packages');
    expect($debugInfo)->toHaveKey('should_preserve_output');
    expect($debugInfo)->toHaveKey('debug_services');
    expect($debugInfo)->toHaveKey('constants');
    expect($debugInfo)->toHaveKey('env_vars');
    
    // 验证调试模式信息
    expect($debugInfo['debug_mode'])->toBeTrue();
    expect($debugInfo['has_think_trace'])->toBeTrue();
    expect($debugInfo['should_preserve_output'])->toBeTrue();
    expect($debugInfo['debug_services'])->toBeArray();
});

it('detects debug mode from various sources', function () {
    // 测试常量检测
    if (defined('APP_DEBUG')) {
        expect(DebugHelper::isDebugMode())->toBeTrue();
    }
    
    // 测试环境变量检测
    $oldEnv = $_ENV['APP_DEBUG'] ?? null;
    $_ENV['APP_DEBUG'] = true;
    expect(DebugHelper::isDebugMode())->toBeTrue();
    
    $_ENV['APP_DEBUG'] = 'true';
    expect(DebugHelper::isDebugMode())->toBeTrue();
    
    // 恢复环境变量
    if ($oldEnv !== null) {
        $_ENV['APP_DEBUG'] = $oldEnv;
    } else {
        unset($_ENV['APP_DEBUG']);
    }
});

it('can detect think-trace from file system', function () {
    // 检查 think-trace 包是否存在
    $thinkTracePath = getcwd() . '/vendor/topthink/think-trace';
    $fileExists = file_exists($thinkTracePath);
    
    expect(DebugHelper::hasThinkTrace())->toBe($fileExists);
});

it('provides correct debug services based on mode', function () {
    $services = DebugHelper::getDebugServices();
    
    if (DebugHelper::isDebugMode()) {
        expect($services)->not->toBeEmpty();
        expect($services)->toContain('log');
    } else {
        expect($services)->toBeEmpty();
    }
});
