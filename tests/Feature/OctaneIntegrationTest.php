<?php

declare(strict_types=1);

use yangweijie\thinkOctane\OctaneService;
use yangweijie\thinkOctane\Server\SwooleServer;
use yangweijie\thinkOctane\Manager\ApplicationManager;
use yangweijie\thinkOctane\Manager\MemoryManager;
use think\Request;
use think\Response;

it('can integrate octane service with thinkphp application', function () {
    $app = $this->getApp();
    
    // 注册Octane服务
    $service = new OctaneService($app);
    $service->register();
    $service->boot();
    
    // 验证服务集成
    expect($app->has(ApplicationManager::class))->toBeTrue();
    expect($app->has(MemoryManager::class))->toBeTrue();
});

it('can handle complete request lifecycle', function () {
    $app = $this->getApp();
    
    // 设置HTTP服务模拟
    $app->http = new class {
        public function run($request) {
            return Response::create('Hello from Octane!', 'html', 200);
        }
    };
    
    // 创建应用管理器
    $applicationManager = new ApplicationManager($app);
    $memoryManager = new MemoryManager();
    
    // 创建模拟请求
    $request = $this->createMockRequest([
        'method' => 'GET',
        'uri' => '/',
        'header' => ['Host' => 'localhost'],
    ]);
    
    // 处理请求
    $response = $applicationManager->handle($request);
    
    // 验证响应
    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getContent())->toBe('Hello from Octane!');
    expect($response->getCode())->toBe(200);
    
    // 验证请求计数
    expect($applicationManager->getRequestCount())->toBe(1);
    
    // 清理内存
    $memoryManager->flush();
    expect($memoryManager->getRequestCount())->toBe(1);
});

it('can handle multiple requests with memory management', function () {
    $app = $this->getApp();

    // 设置HTTP服务模拟
    $requestCount = 0;
    $app->http = new class {
        private $requestCount = 0;

        public function run($request) {
            $this->requestCount++;
            return Response::create("Request #{$this->requestCount}", 'html', 200);
        }

        public function getRequestCount() {
            return $this->requestCount;
        }
    };
    
    $applicationManager = new ApplicationManager($app);
    $memoryManager = new MemoryManager();
    
    // 处理多个请求
    for ($i = 1; $i <= 5; $i++) {
        $request = $this->createMockRequest();
        $response = $applicationManager->handle($request);

        expect($response->getContent())->toBe("Request #{$i}");
        expect($applicationManager->getRequestCount())->toBe($i);

        // 每次请求后清理内存
        $memoryManager->flush();
    }
    
    expect($memoryManager->getRequestCount())->toBe(5);
});

it('can handle server configuration and status', function () {
    $app = $this->getApp();
    $config = $app->config->get('octane');
    
    $server = new SwooleServer($app, $config);
    
    // 测试配置
    expect($server->getConfig('host'))->toBe('127.0.0.1');
    expect($server->getConfig('port'))->toBe(8000);
    
    // 测试状态
    $status = $server->status();
    expect($status)->toBeArray();
    expect($status['server'])->toBe('swoole');
    expect($status['running'])->toBeFalse();
    
    // 测试PID文件路径
    $pidFile = $server->getPidFile();
    expect($pidFile)->toContain('octane_swoole.pid');
});

it('can handle application warm up and flush', function () {
    $app = $this->getApp();
    
    // 配置预热和清理服务
    $this->mockConfig([
        'warm' => ['test_warm_service'],
        'flush' => ['test_flush_service'],
        'server' => 'swoole',
    ]);
    
    // 注册测试服务
    $app->bind('test_warm_service', function () {
        return new class {
            public $warmed = true;
        };
    });
    
    $app->bind('test_flush_service', function () {
        return new class {
            public $data = 'test';
        };
    });
    
    $applicationManager = new ApplicationManager($app);
    
    // 测试预热
    $applicationManager->warm();
    expect($app->has('test_warm_service'))->toBeTrue();
    
    // 实例化需要清理的服务
    $flushService = $app->make('test_flush_service');
    expect($flushService->data)->toBe('test');
    
    // 测试清理应该不会抛出异常
    expect(function () use ($applicationManager) {
        $applicationManager->flush();
    })->not->toThrow(\Exception::class);
});

it('can handle memory limit checking', function () {
    $memoryManager = new MemoryManager();
    
    // 测试内存限制检查
    $isExceeded = $memoryManager->isMemoryLimitExceeded(0.9);
    expect($isExceeded)->toBeBool();
    
    // 测试内存使用统计
    $usage = $memoryManager->getMemoryUsage();
    expect($usage)->toHaveKeys([
        'memory_usage',
        'memory_peak_usage',
        'memory_limit',
        'memory_usage_formatted',
        'memory_peak_usage_formatted',
    ]);
    
    expect($usage['memory_usage'])->toBeInt();
    expect($usage['memory_peak_usage'])->toBeInt();
    expect($usage['memory_usage_formatted'])->toBeString();
});

it('can handle error scenarios gracefully', function () {
    $app = $this->getApp();
    
    // 模拟抛出异常的HTTP服务
    $app->http = new class {
        public function run($request) {
            throw new \RuntimeException('Simulated error');
        }
    };
    
    $applicationManager = new ApplicationManager($app);
    $request = $this->createMockRequest();
    
    // 处理请求应该返回错误响应而不是抛出异常
    $response = $applicationManager->handle($request);
    
    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getCode())->toBe(500);
    
    $content = json_decode($response->getContent(), true);
    expect($content)->toHaveKey('error');
    expect($content['error'])->toBe('Application Error');
});
