<?php

declare(strict_types=1);

use yangweijie\thinkOctane\Manager\ApplicationManager;
use yangweijie\thinkOctane\Support\ThinkPHPResetter;
use yangweijie\thinkOctane\Support\DebugHelper;

it('resets think-trace runtime between requests', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);
    
    // 确保在调试模式下
    if (!DebugHelper::isDebugMode()) {
        $this->markTestSkipped('This test requires debug mode to be enabled');
    }
    
    // 模拟第一个请求
    $request1 = $this->createMockRequest();
    
    // 设置一个旧的开始时间
    $oldTime = microtime(true) - 100; // 100秒前
    $_SERVER['REQUEST_TIME_FLOAT'] = $oldTime;
    $GLOBALS['_think_start_time'] = $oldTime;
    
    // 处理第一个请求
    $response1 = $manager->handle($request1);
    expect($response1)->toBeInstanceOf(\think\Response::class);
    
    // 验证时间被重置
    expect($_SERVER['REQUEST_TIME_FLOAT'])->toBeGreaterThan($oldTime);
    expect($GLOBALS['_think_start_time'])->toBeGreaterThan($oldTime);
    
    $firstRequestTime = $_SERVER['REQUEST_TIME_FLOAT'];
    
    // 等待一小段时间
    usleep(10000); // 10ms
    
    // 模拟第二个请求
    $request2 = $this->createMockRequest();
    
    // 处理第二个请求
    $response2 = $manager->handle($request2);
    expect($response2)->toBeInstanceOf(\think\Response::class);
    
    // 验证第二个请求的时间被重新重置
    expect($_SERVER['REQUEST_TIME_FLOAT'])->toBeGreaterThan($firstRequestTime);
    expect($GLOBALS['_think_start_time'])->toBeGreaterThan($firstRequestTime);
});

it('preserves debug functionality after reset', function () {
    $app = $this->getApp();
    
    // 执行重置
    ThinkPHPResetter::resetApp($app);
    
    // 验证调试功能仍然可用
    expect(DebugHelper::isDebugMode())->toBeTrue();
    expect(DebugHelper::hasThinkTrace())->toBeTrue();
    expect(DebugHelper::shouldPreserveDebugOutput())->toBeTrue();
    
    // 验证调试服务仍然受保护
    $debugServices = DebugHelper::getDebugServices();
    expect($debugServices)->toContain('log');
    expect($debugServices)->toContain('trace');
    expect($debugServices)->toContain('debug');
    expect($debugServices)->toContain('middleware');
});

it('clears trace globals but preserves functionality', function () {
    $app = $this->getApp();
    
    // 设置一些 trace 相关的全局变量
    $GLOBALS['_trace_tabs'] = ['test' => 'data'];
    $GLOBALS['_trace_data'] = ['debug' => 'info'];
    $GLOBALS['_trace_info'] = ['request' => 'data'];
    
    // 执行重置
    ThinkPHPResetter::resetApp($app);
    
    // 验证 trace 全局变量被清理
    expect(isset($GLOBALS['_trace_tabs']))->toBeFalse();
    expect(isset($GLOBALS['_trace_data']))->toBeFalse();
    expect(isset($GLOBALS['_trace_info']))->toBeFalse();
    
    // 但调试功能仍然可用
    expect(DebugHelper::hasThinkTrace())->toBeTrue();
});

it('resets memory statistics correctly', function () {
    $app = $this->getApp();
    
    $beforeMemory = memory_get_usage();
    
    // 设置旧的内存统计
    $oldMemory = $beforeMemory - 1024 * 1024; // 1MB前
    $GLOBALS['_think_start_mem'] = $oldMemory;
    
    // 执行重置
    ThinkPHPResetter::resetApp($app);
    
    // 验证内存统计被重置到当前值附近
    expect($GLOBALS['_think_start_mem'])->toBeGreaterThan($oldMemory);
    expect($GLOBALS['_think_start_mem'])->toBeGreaterThanOrEqual($beforeMemory - 1024); // 允许小幅波动
    expect($GLOBALS['_app_start_mem'])->toBeGreaterThanOrEqual($beforeMemory - 1024);
});

it('handles multiple consecutive resets correctly', function () {
    $app = $this->getApp();
    
    $times = [];
    $memories = [];
    
    // 执行多次重置
    for ($i = 0; $i < 5; $i++) {
        ThinkPHPResetter::resetApp($app);
        
        $times[] = $_SERVER['REQUEST_TIME_FLOAT'];
        $memories[] = $GLOBALS['_think_start_mem'];
        
        usleep(1000); // 1ms间隔
    }
    
    // 验证每次重置都更新了时间
    for ($i = 1; $i < count($times); $i++) {
        expect($times[$i])->toBeGreaterThanOrEqual($times[$i - 1]);
    }
    
    // 验证内存统计是合理的
    foreach ($memories as $memory) {
        expect($memory)->toBeInt();
        expect($memory)->toBeGreaterThan(0);
    }
});

it('preserves output buffer in debug mode during reset', function () {
    $app = $this->getApp();
    
    // 创建输出缓冲区
    ob_start();
    echo "debug output for think-trace";
    $initialLevel = ob_get_level();
    $initialContent = ob_get_contents();
    
    // 执行重置
    ThinkPHPResetter::resetApp($app);
    
    // 在调试模式下，输出缓冲区应该被保留
    if (DebugHelper::isDebugMode()) {
        expect(ob_get_level())->toBe($initialLevel);
        expect(ob_get_contents())->toBe($initialContent);
    }
    
    // 清理测试缓冲区
    ob_end_clean();
});

it('resets request state variables', function () {
    $app = $this->getApp();
    
    // 设置一些请求状态变量
    $GLOBALS['_think_request_id'] = 'test-request-123';
    $GLOBALS['_think_response_time'] = 123.456;
    $GLOBALS['_think_cache_stats'] = ['reads' => 10, 'writes' => 5];
    $GLOBALS['_think_db_queries'] = 15;
    
    // 执行重置
    ThinkPHPResetter::resetApp($app);
    
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

it('provides accurate reset statistics', function () {
    $app = $this->getApp();
    
    $beforeReset = microtime(true);
    
    // 执行重置
    ThinkPHPResetter::resetApp($app);
    
    $afterReset = microtime(true);
    
    // 获取重置统计
    $stats = ThinkPHPResetter::getResetStats();
    
    expect($stats)->toBeArray();
    expect($stats)->toHaveKey('reset_time');
    expect($stats)->toHaveKey('current_memory');
    expect($stats)->toHaveKey('peak_memory');
    expect($stats)->toHaveKey('globals_reset');
    
    // 验证重置时间在合理范围内
    expect($stats['reset_time'])->toBeGreaterThanOrEqual($beforeReset);
    expect($stats['reset_time'])->toBeLessThanOrEqual($afterReset);
    
    // 验证内存统计
    expect($stats['current_memory'])->toBeInt();
    expect($stats['peak_memory'])->toBeInt();
    expect($stats['current_memory'])->toBeGreaterThan(0);
    expect($stats['peak_memory'])->toBeGreaterThanOrEqual($stats['current_memory']);
    
    // 验证全局变量重置状态
    expect($stats['globals_reset'])->toBeArray();
    expect($stats['globals_reset'])->toHaveKey('_think_start_time');
    expect($stats['globals_reset'])->toHaveKey('_think_start_mem');
});
