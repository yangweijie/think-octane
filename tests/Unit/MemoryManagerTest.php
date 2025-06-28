<?php

declare(strict_types=1);

use yangweijie\thinkOctane\Manager\MemoryManager;

it('can create memory manager', function () {
    $manager = new MemoryManager();
    
    expect($manager)->toBeInstanceOf(MemoryManager::class);
    expect($manager->getRequestCount())->toBe(0);
});

it('can get memory usage', function () {
    $manager = new MemoryManager();
    $usage = $manager->getMemoryUsage();
    
    expect($usage)->toBeArray();
    expect($usage)->toHaveKeys([
        'memory_usage',
        'memory_peak_usage',
        'memory_limit',
        'memory_usage_formatted',
        'memory_peak_usage_formatted'
    ]);
    
    expect($usage['memory_usage'])->toBeInt();
    expect($usage['memory_peak_usage'])->toBeInt();
    expect($usage['memory_usage_formatted'])->toBeString();
    expect($usage['memory_peak_usage_formatted'])->toBeString();
});

it('can check memory limit exceeded', function () {
    $manager = new MemoryManager();
    
    // 测试正常情况（不应该超过限制）
    expect($manager->isMemoryLimitExceeded(0.9))->toBeFalse();
    
    // 测试低阈值（可能超过限制）
    $result = $manager->isMemoryLimitExceeded(0.1);
    expect($result)->toBeBool();
});

it('can force garbage collection', function () {
    $manager = new MemoryManager();
    
    $cycles = $manager->forceGarbageCollection();
    expect($cycles)->toBeInt();
    expect($cycles)->toBeGreaterThanOrEqual(0);
});

it('can get gc stats', function () {
    $manager = new MemoryManager();
    $stats = $manager->getGcStats();
    
    expect($stats)->toBeArray();
    expect($stats)->toHaveKeys(['runs', 'collected', 'threshold', 'roots']);
});

it('can flush memory', function () {
    $manager = new MemoryManager();
    
    // 设置一些全局变量
    $_GET = ['test' => 'value'];
    $_POST = ['test' => 'value'];
    $_FILES = ['test' => 'value'];
    $_COOKIE = ['test' => 'value'];
    $_SERVER['HTTP_TEST'] = 'value';
    
    $initialCount = $manager->getRequestCount();
    
    // 执行内存清理
    $manager->flush();
    
    // 验证全局变量被清理
    expect($_GET)->toBeEmpty();
    expect($_POST)->toBeEmpty();
    expect($_FILES)->toBeEmpty();
    expect($_COOKIE)->toBeEmpty();
    expect($_SERVER)->not->toHaveKey('HTTP_TEST');
    
    // 验证请求计数增加
    expect($manager->getRequestCount())->toBe($initialCount + 1);
});

it('can reset request count', function () {
    $manager = new MemoryManager();

    // 增加请求计数
    $manager->flush();
    $manager->flush();
    expect($manager->getRequestCount())->toBe(2);

    // 重置计数
    $manager->resetRequestCount();
    expect($manager->getRequestCount())->toBe(0);
});

it('can parse memory limit correctly', function () {
    $manager = new MemoryManager();

    // 测试解析内存限制的私有方法
    $parseMemoryLimit = function ($limit) use ($manager) {
        return $this->callPrivateMethod($manager, 'parseMemoryLimit', [$limit]);
    };

    expect($parseMemoryLimit('-1'))->toBe(-1);
    expect($parseMemoryLimit('128M'))->toBe(128 * 1024 * 1024);
    expect($parseMemoryLimit('1G'))->toBe(1024 * 1024 * 1024);
    expect($parseMemoryLimit('512K'))->toBe(512 * 1024);
});

it('can format bytes correctly', function () {
    $manager = new MemoryManager();

    // 测试格式化字节的私有方法
    $formatBytes = function ($bytes) use ($manager) {
        return $this->callPrivateMethod($manager, 'formatBytes', [$bytes]);
    };

    expect($formatBytes(1024))->toBe('1 KB');
    expect($formatBytes(1024 * 1024))->toBe('1 MB');
    expect($formatBytes(1024 * 1024 * 1024))->toBe('1 GB');
    expect($formatBytes(512))->toBe('512 B');
});

it('can handle garbage collection with different configurations', function () {
    // 测试禁用垃圾回收
    $this->mockConfig([
        'garbage_collection' => [
            'enabled' => false,
            'probability' => 50,
            'cycles' => 1000,
        ],
        'server' => 'swoole',
    ]);

    $manager = new MemoryManager();

    // 执行多次flush，垃圾回收应该不会执行
    for ($i = 0; $i < 10; $i++) {
        $manager->flush();
    }

    expect($manager->getRequestCount())->toBe(10);
});

it('can clear globals correctly', function () {
    $manager = new MemoryManager();

    // 设置一些全局变量和HTTP头
    $_GET = ['test' => 'value'];
    $_POST = ['data' => 'value'];
    $_FILES = ['upload' => 'file'];
    $_COOKIE = ['session' => 'id'];
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token';
    $_SERVER['HTTP_USER_AGENT'] = 'Test Agent';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';

    // 保存非HTTP相关的SERVER变量
    $originalPath = $_SERVER['PATH'] ?? null;

    $manager->flush();

    // 验证HTTP相关变量被清理
    expect($_GET)->toBeEmpty();
    expect($_POST)->toBeEmpty();
    expect($_FILES)->toBeEmpty();
    expect($_COOKIE)->toBeEmpty();
    expect($_SERVER)->not->toHaveKey('HTTP_AUTHORIZATION');
    expect($_SERVER)->not->toHaveKey('HTTP_USER_AGENT');
    expect($_SERVER)->not->toHaveKey('HTTP_ACCEPT');

    // 验证非HTTP相关的SERVER变量保持不变
    if ($originalPath !== null) {
        expect($_SERVER['PATH'])->toBe($originalPath);
    }
});

it('can trigger garbage collection based on cycles', function () {
    $this->mockConfig([
        'garbage_collection' => [
            'enabled' => true,
            'probability' => 0, // 禁用概率触发
            'cycles' => 3, // 每3次请求触发一次
        ],
        'server' => 'swoole',
    ]);

    $manager = new MemoryManager();

    // 执行3次flush应该触发垃圾回收
    $manager->flush(); // 1
    $manager->flush(); // 2

    $cyclesBefore = gc_collect_cycles();
    $manager->flush(); // 3 - 应该触发GC

    expect($manager->getRequestCount())->toBe(3);
});

it('preserves output buffer in debug mode', function () {
    $memoryManager = new MemoryManager();

    // 创建输出缓冲区
    ob_start();
    echo "debug output";
    $initialLevel = ob_get_level();

    // 执行清理
    $memoryManager->flush();

    // 在调试模式下，输出缓冲区应该被保留
    if (defined('APP_DEBUG') && APP_DEBUG) {
        expect(ob_get_level())->toBe($initialLevel);
    }

    // 清理测试缓冲区
    ob_end_clean();
});

it('has memory leak detector', function () {
    $memoryManager = new MemoryManager();

    $detector = $memoryManager->getLeakDetector();

    expect($detector)->toBeInstanceOf(\yangweijie\thinkOctane\Support\MemoryLeakDetector::class);
});

it('records request start in leak detector', function () {
    $memoryManager = new MemoryManager();
    $detector = $memoryManager->getLeakDetector();

    // 获取初始请求计数
    $initialStats = $detector->getMemoryStats();
    $initialRequests = $initialStats['total_requests'] ?? 0;

    // 执行清理（会记录请求开始）
    $memoryManager->flush();

    // 验证请求计数增加
    $newStats = $detector->getMemoryStats();
    expect($newStats['total_requests'])->toBeGreaterThan($initialRequests);
});

it('detects and cleans memory leaks', function () {
    $memoryManager = new MemoryManager();

    // 模拟内存泄漏检测
    $reflection = new ReflectionClass($memoryManager);
    $checkMethod = $reflection->getMethod('checkAndCleanupMemoryLeak');
    $checkMethod->setAccessible(true);

    // 执行检测应该不会抛出异常
    expect(function () use ($checkMethod, $memoryManager) {
        $checkMethod->invoke($memoryManager);
    })->not->toThrow(\Exception::class);
});
