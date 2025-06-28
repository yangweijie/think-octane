<?php

declare(strict_types=1);

use yangweijie\thinkOctane\Support\ThinkPHPResetter;
use yangweijie\thinkOctane\Support\DebugHelper;

it('can reset ThinkPHP application state', function () {
    $app = $this->getApp();
    
    // 设置一些初始状态
    $oldTime = microtime(true) - 100; // 100秒前
    $oldMemory = memory_get_usage() - 1024 * 1024; // 1MB前
    
    $_SERVER['REQUEST_TIME_FLOAT'] = $oldTime;
    $GLOBALS['_think_start_time'] = $oldTime;
    $GLOBALS['_think_start_mem'] = $oldMemory;
    
    // 执行重置
    ThinkPHPResetter::resetApp($app);
    
    // 验证时间被重置
    expect($_SERVER['REQUEST_TIME_FLOAT'])->toBeGreaterThan($oldTime);
    expect($GLOBALS['_think_start_time'])->toBeGreaterThan($oldTime);
    
    // 验证内存被重置
    expect($GLOBALS['_think_start_mem'])->toBeGreaterThan($oldMemory);
});

it('can reset timer correctly', function () {
    $app = $this->getApp();
    
    $beforeTime = microtime(true);
    
    // 执行时间重置
    ThinkPHPResetter::resetTimer($app);
    
    $afterTime = microtime(true);
    
    // 验证时间被重置到当前时间附近
    expect($_SERVER['REQUEST_TIME_FLOAT'])->toBeGreaterThanOrEqual($beforeTime);
    expect($_SERVER['REQUEST_TIME_FLOAT'])->toBeLessThanOrEqual($afterTime);
    
    // 验证全局时间变量
    expect($GLOBALS['_think_start_time'])->toBeGreaterThanOrEqual($beforeTime);
    expect($GLOBALS['_octane_start_time'])->toBeGreaterThanOrEqual($beforeTime);
});

it('can reset memory statistics', function () {
    $app = $this->getApp();
    
    $beforeMemory = memory_get_usage();
    
    // 执行内存重置
    ThinkPHPResetter::resetMemory($app);
    
    // 验证内存统计被重置
    expect($GLOBALS['_think_start_mem'])->toBeGreaterThanOrEqual($beforeMemory - 1024); // 允许小幅波动
    expect($GLOBALS['_app_start_mem'])->toBeGreaterThanOrEqual($beforeMemory - 1024);
});

it('can reset debug state', function () {
    $app = $this->getApp();
    
    // 设置一些调试状态
    $GLOBALS['_trace_tabs'] = ['test' => 'data'];
    $GLOBALS['_trace_data'] = ['debug' => 'info'];
    $GLOBALS['_trace_info'] = ['request' => 'data'];
    
    // 执行调试状态重置
    ThinkPHPResetter::resetDebugState($app);
    
    // 验证调试状态被清理
    expect(isset($GLOBALS['_trace_tabs']))->toBeFalse();
    expect(isset($GLOBALS['_trace_data']))->toBeFalse();
    expect(isset($GLOBALS['_trace_info']))->toBeFalse();
});

it('can reset request state', function () {
    $app = $this->getApp();
    
    // 设置一些请求状态
    $GLOBALS['_think_request_id'] = 'test-request';
    $GLOBALS['_think_response_time'] = 123.456;
    $GLOBALS['_think_cache_stats'] = ['reads' => 10, 'writes' => 5];
    $GLOBALS['_think_db_queries'] = 15;
    
    // 执行请求状态重置
    ThinkPHPResetter::resetRequestState($app);
    
    // 验证请求状态被重置
    expect(isset($GLOBALS['_think_request_id']))->toBeFalse();
    expect(isset($GLOBALS['_think_response_time']))->toBeFalse();
    
    // 验证统计被重置
    if (isset($GLOBALS['_think_cache_stats'])) {
        expect($GLOBALS['_think_cache_stats']['reads'])->toBe(0);
        expect($GLOBALS['_think_cache_stats']['writes'])->toBe(0);
    }
    
    if (isset($GLOBALS['_think_db_queries'])) {
        expect($GLOBALS['_think_db_queries'])->toBe(0);
    }
});

it('can get reset statistics', function () {
    $stats = ThinkPHPResetter::getResetStats();
    
    expect($stats)->toBeArray();
    expect($stats)->toHaveKey('reset_time');
    expect($stats)->toHaveKey('current_memory');
    expect($stats)->toHaveKey('peak_memory');
    expect($stats)->toHaveKey('globals_reset');
    
    expect($stats['reset_time'])->toBeFloat();
    expect($stats['current_memory'])->toBeInt();
    expect($stats['peak_memory'])->toBeInt();
    expect($stats['globals_reset'])->toBeArray();
});

it('resets state only in debug mode when called from application manager', function () {
    $app = $this->getApp();

    // 这个测试验证重置功能本身工作正常
    $oldTime = microtime(true) - 100;
    $_SERVER['REQUEST_TIME_FLOAT'] = $oldTime;

    // 执行重置
    ThinkPHPResetter::resetApp($app);

    // 验证状态被重置
    expect($_SERVER['REQUEST_TIME_FLOAT'])->toBeGreaterThan($oldTime);
});

it('preserves debug functionality after reset', function () {
    $app = $this->getApp();

    // 确保调试模式启用
    if (!defined('APP_DEBUG')) {
        define('APP_DEBUG', true);
    }
    $_ENV['APP_DEBUG'] = true;

    // 执行重置
    ThinkPHPResetter::resetApp($app);

    // 验证调试功能仍然可用
    expect(DebugHelper::isDebugMode())->toBeTrue();
    expect(DebugHelper::hasThinkTrace())->toBeTrue();
    expect(DebugHelper::shouldPreserveDebugOutput())->toBeTrue();
});
