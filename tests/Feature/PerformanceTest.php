<?php

declare(strict_types=1);

use yangweijie\thinkOctane\Manager\ApplicationManager;
use yangweijie\thinkOctane\Manager\MemoryManager;
use think\Response;

it('can handle high request volume efficiently', function () {
    $app = $this->getApp();
    
    // 设置简单的HTTP服务
    $app->http = new class {
        public function run($request) {
            return Response::create('OK', 'html', 200);
        }
    };
    
    $applicationManager = new ApplicationManager($app);
    $memoryManager = new MemoryManager();
    
    $startTime = microtime(true);
    $requestCount = 100;
    
    // 处理大量请求
    for ($i = 0; $i < $requestCount; $i++) {
        $request = $this->createMockRequest();
        $response = $applicationManager->handle($request);
        
        expect($response->getCode())->toBe(200);
        
        // 每10个请求清理一次内存
        if ($i % 10 === 0) {
            $memoryManager->flush();
        }
    }
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    // 验证性能（应该在合理时间内完成）
    expect($duration)->toBeLessThan(5.0); // 5秒内完成100个请求
    expect($applicationManager->getRequestCount())->toBe($requestCount);
});

it('can manage memory efficiently during stress test', function () {
    $memoryManager = new MemoryManager();
    
    $initialMemory = memory_get_usage(true);
    
    // 模拟大量内存操作
    for ($i = 0; $i < 1000; $i++) {
        // 设置全局变量
        $_GET['test_' . $i] = str_repeat('x', 100);
        $_POST['data_' . $i] = str_repeat('y', 100);
        $_SERVER['HTTP_TEST_' . $i] = str_repeat('z', 100);
        
        // 每100次操作清理一次
        if ($i % 100 === 0) {
            $memoryManager->flush();
        }
    }
    
    // 最终清理
    $memoryManager->flush();
    
    $finalMemory = memory_get_usage(true);
    
    // 验证内存没有显著增长
    $memoryIncrease = $finalMemory - $initialMemory;
    expect($memoryIncrease)->toBeLessThan(1024 * 1024); // 小于1MB增长
});

it('can handle concurrent-like request simulation', function () {
    $app = $this->getApp();
    
    // 模拟不同类型的请求处理
    $app->http = new class {
        public function run($request) {
            // 模拟不同的处理时间
            $delay = rand(1, 10) * 1000; // 1-10毫秒
            usleep($delay);
            
            return Response::create('Processed', 'html', 200);
        }
    };
    
    $applicationManager = new ApplicationManager($app);
    $memoryManager = new MemoryManager();
    
    $startTime = microtime(true);
    $successCount = 0;
    
    // 模拟50个"并发"请求
    for ($i = 0; $i < 50; $i++) {
        $request = $this->createMockRequest([
            'uri' => '/test/' . $i,
            'method' => $i % 2 === 0 ? 'GET' : 'POST',
        ]);
        
        $response = $applicationManager->handle($request);
        
        if ($response->getCode() === 200) {
            $successCount++;
        }
        
        $memoryManager->flush();
    }
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    // 验证所有请求都成功处理
    expect($successCount)->toBe(50);
    expect($duration)->toBeLessThan(2.0); // 2秒内完成
});

it('can handle memory pressure gracefully', function () {
    $memoryManager = new MemoryManager();
    
    // 获取当前内存使用情况
    $initialUsage = $memoryManager->getMemoryUsage();
    
    // 创建大量数据模拟内存压力
    $largeData = [];
    for ($i = 0; $i < 1000; $i++) {
        $largeData[] = str_repeat('test_data_' . $i, 100);
        
        // 每100次迭代检查内存
        if ($i % 100 === 0) {
            $currentUsage = $memoryManager->getMemoryUsage();
            
            // 如果内存使用过高，执行清理
            if ($memoryManager->isMemoryLimitExceeded(0.7)) {
                $memoryManager->forceGarbageCollection();
                unset($largeData);
                $largeData = [];
            }
        }
    }
    
    // 最终清理
    unset($largeData);
    $memoryManager->forceGarbageCollection();
    
    $finalUsage = $memoryManager->getMemoryUsage();
    
    // 验证内存管理有效
    expect($finalUsage['memory_usage'])->toBeInt();
    expect($finalUsage['memory_peak_usage'])->toBeGreaterThanOrEqual($initialUsage['memory_usage']);
});

it('maintains consistent performance in debug mode', function () {
    $app = $this->getApp();
    $applicationManager = new ApplicationManager($app);
    $memoryManager = new MemoryManager();

    // 检查是否在调试模式下
    if (!defined('APP_DEBUG') || !APP_DEBUG) {
        $this->markTestSkipped('This test requires debug mode');
    }

    $requestTimes = [];
    $memoryUsages = [];

    // 执行多个请求并记录性能
    for ($i = 0; $i < 10; $i++) {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // 创建请求
        $request = $this->createMockRequest();

        // 处理请求
        $response = $applicationManager->handle($request);
        expect($response)->toBeInstanceOf(\think\Response::class);

        // 清理内存
        $memoryManager->flush();

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $requestTimes[] = $endTime - $startTime;
        $memoryUsages[] = $endMemory - $startMemory;
    }

    // 验证性能一致性
    $avgTime = array_sum($requestTimes) / count($requestTimes);
    $avgMemory = array_sum($memoryUsages) / count($memoryUsages);

    // 每个请求的时间应该相对一致（变化不超过平均值的50%）
    foreach ($requestTimes as $time) {
        expect($time)->toBeLessThan($avgTime * 1.5);
        expect($time)->toBeGreaterThan($avgTime * 0.5);
    }

    // 内存使用应该相对稳定
    expect($avgMemory)->toBeLessThan(1024 * 1024); // 平均每请求不超过1MB
});

it('resets timing correctly between requests in debug mode', function () {
    $app = $this->getApp();
    $applicationManager = new ApplicationManager($app);

    if (!defined('APP_DEBUG') || !APP_DEBUG) {
        $this->markTestSkipped('This test requires debug mode');
    }

    $requestStartTimes = [];

    // 执行多个请求
    for ($i = 0; $i < 5; $i++) {
        // 记录请求前的时间
        $beforeRequest = microtime(true);

        // 处理请求
        $request = $this->createMockRequest();
        $response = $applicationManager->handle($request);
        expect($response)->toBeInstanceOf(\think\Response::class);

        // 记录请求后的时间状态
        $requestStartTimes[] = $_SERVER['REQUEST_TIME_FLOAT'];

        // 等待一小段时间
        usleep(5000); // 5ms
    }

    // 验证每个请求的开始时间都被正确重置
    for ($i = 1; $i < count($requestStartTimes); $i++) {
        expect($requestStartTimes[$i])->toBeGreaterThan($requestStartTimes[$i - 1]);
    }
});

it('can measure garbage collection effectiveness', function () {
    $memoryManager = new MemoryManager();
    
    // 获取初始GC统计
    $initialStats = $memoryManager->getGcStats();
    
    // 创建循环引用以触发垃圾回收
    for ($i = 0; $i < 100; $i++) {
        $obj1 = new stdClass();
        $obj2 = new stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;
        
        // 每10次迭代强制垃圾回收
        if ($i % 10 === 0) {
            $collected = $memoryManager->forceGarbageCollection();
            expect($collected)->toBeInt();
        }
    }
    
    // 最终垃圾回收
    $finalCollected = $memoryManager->forceGarbageCollection();
    $finalStats = $memoryManager->getGcStats();
    
    // 验证垃圾回收工作正常
    expect($finalCollected)->toBeGreaterThanOrEqual(0);
    expect($finalStats['runs'])->toBeGreaterThanOrEqual($initialStats['runs']);
});
