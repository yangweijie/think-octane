<?php

declare(strict_types=1);

use yangweijie\thinkOctane\Support\MemoryLeakDetector;

it('can record request start', function () {
    $detector = new MemoryLeakDetector();
    
    $initialStats = $detector->getMemoryStats();
    $initialCount = $initialStats['total_requests'] ?? 0;
    
    // 记录请求开始
    $detector->recordRequestStart();
    
    $newStats = $detector->getMemoryStats();
    expect($newStats['total_requests'])->toBe($initialCount + 1);
});

it('can detect memory leaks', function () {
    $detector = new MemoryLeakDetector();
    
    // 初始状态应该没有足够数据
    $leak = $detector->detectLeak();
    expect($leak['leak_detected'])->toBeFalse();
    expect($leak['message'])->toBe('Not enough data');
    
    // 记录多个请求以获得足够数据
    for ($i = 0; $i < 30; $i++) {
        $detector->recordRequestStart();
        // 模拟一些内存使用
        usleep(1000); // 1ms
    }
    
    $leak = $detector->detectLeak();
    expect($leak)->toHaveKey('leak_detected');
    expect($leak)->toHaveKey('memory_growth');
    expect($leak)->toHaveKey('memory_growth_formatted');
    expect($leak)->toHaveKey('message');
});

it('can get memory statistics', function () {
    $detector = new MemoryLeakDetector();
    
    // 记录一些请求
    for ($i = 0; $i < 15; $i++) {
        $detector->recordRequestStart();
    }
    
    $stats = $detector->getMemoryStats();
    
    expect($stats)->toBeArray();
    expect($stats)->toHaveKey('total_requests');
    expect($stats)->toHaveKey('tracked_requests');
    expect($stats)->toHaveKey('current_memory');
    expect($stats)->toHaveKey('current_memory_formatted');
    
    expect($stats['total_requests'])->toBe(15);
    expect($stats['tracked_requests'])->toBeGreaterThan(0);
});

it('can reset detector state', function () {
    $detector = new MemoryLeakDetector();
    
    // 记录一些请求
    for ($i = 0; $i < 5; $i++) {
        $detector->recordRequestStart();
    }
    
    $statsBeforeReset = $detector->getMemoryStats();
    expect($statsBeforeReset['total_requests'])->toBe(5);
    
    // 重置
    $detector->reset();
    
    $statsAfterReset = $detector->getMemoryStats();
    expect($statsAfterReset)->toBeEmpty();
});

it('can format bytes correctly', function () {
    $detector = new MemoryLeakDetector();
    
    // 使用反射访问私有方法
    $reflection = new ReflectionClass($detector);
    $formatBytes = $reflection->getMethod('formatBytes');
    $formatBytes->setAccessible(true);
    
    expect($formatBytes->invoke($detector, 1024))->toBe('1 KB');
    expect($formatBytes->invoke($detector, 1024 * 1024))->toBe('1 MB');
    expect($formatBytes->invoke($detector, 1024 * 1024 * 1024))->toBe('1 GB');
    expect($formatBytes->invoke($detector, 512))->toBe('512 B');
    expect($formatBytes->invoke($detector, 0))->toBe('0 B');
});

it('can get cleanup suggestions', function () {
    $detector = new MemoryLeakDetector();
    
    // 没有泄漏时应该没有建议
    $suggestions = $detector->getCleanupSuggestions();
    expect($suggestions)->toBeArray();
    
    // 记录足够的请求以可能触发建议
    for ($i = 0; $i < 30; $i++) {
        $detector->recordRequestStart();
    }
    
    $suggestions = $detector->getCleanupSuggestions();
    expect($suggestions)->toBeArray();
});

it('can perform cleanup operations', function () {
    $detector = new MemoryLeakDetector();
    
    $performed = $detector->performCleanup();
    
    expect($performed)->toBeArray();
    expect($performed)->not->toBeEmpty();
    
    // 应该包含垃圾回收信息
    $gcMessage = array_filter($performed, function ($message) {
        return strpos($message, 'Garbage collection') !== false;
    });
    expect($gcMessage)->not->toBeEmpty();
});

it('maintains memory history correctly', function () {
    $detector = new MemoryLeakDetector();
    
    // 记录请求，但只有每10个请求才会记录到历史
    for ($i = 1; $i <= 25; $i++) {
        $detector->recordRequestStart();
    }
    
    $stats = $detector->getMemoryStats();
    
    // 应该有历史记录
    expect($stats)->toHaveKey('history');
    expect($stats['history'])->toBeArray();
    expect($stats['tracked_requests'])->toBeGreaterThan(0);
    expect($stats['tracked_requests'])->toBeLessThanOrEqual(3); // 25/10 = 2.5，向下取整为2，加上可能的边界情况
});

it('limits memory history size', function () {
    $detector = new MemoryLeakDetector();
    
    // 记录大量请求以测试历史限制
    for ($i = 1; $i <= 250; $i++) {
        $detector->recordRequestStart();
    }
    
    $stats = $detector->getMemoryStats();
    
    // 历史记录应该被限制在20个以内
    expect(count($stats['history']))->toBeLessThanOrEqual(20);
});

it('calculates growth per request correctly', function () {
    $detector = new MemoryLeakDetector();
    
    // 记录足够的请求
    for ($i = 0; $i < 30; $i++) {
        $detector->recordRequestStart();
    }
    
    $leak = $detector->detectLeak();
    
    if ($leak['leak_detected']) {
        expect($leak)->toHaveKey('growth_per_request');
        expect($leak['growth_per_request'])->toBeFloat();
    } else {
        expect($leak['growth_per_request'])->toBeFloat();
    }
});
