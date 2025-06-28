<?php

declare(strict_types=1);

use yangweijie\thinkOctane\Manager\ApplicationManager;
use think\Request;
use think\Response;
use think\exception\HttpException;

it('can create application manager', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);
    
    expect($manager)->toBeInstanceOf(ApplicationManager::class);
    expect($manager->getRequestCount())->toBe(0);
    expect($manager->getMaxRequests())->toBe(500);
});

it('can set and get max requests', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);
    
    $manager->setMaxRequests(1000);
    expect($manager->getMaxRequests())->toBe(1000);
});

it('can reset request count', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);
    
    // 模拟增加请求计数
    $reflection = new ReflectionClass($manager);
    $property = $reflection->getProperty('requestCount');
    $property->setAccessible(true);
    $property->setValue($manager, 10);
    
    expect($manager->getRequestCount())->toBe(10);
    
    $manager->resetRequestCount();
    expect($manager->getRequestCount())->toBe(0);
});

it('can handle request', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);
    
    // 创建模拟请求
    $request = $app->make(Request::class);
    
    // 模拟HTTP服务
    $app->http = new class {
        public function run($request) {
            return Response::create('Hello World');
        }
    };
    
    $response = $manager->handle($request);
    
    expect($response)->toBeInstanceOf(Response::class);
    expect($manager->getRequestCount())->toBe(1);
});

it('can warm application', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);
    
    // 配置预热服务
    $this->mockConfig([
        'warm' => ['test_service'],
        'server' => 'swoole',
        'host' => '127.0.0.1',
        'port' => 8000,
    ]);
    
    // 注册测试服务
    $app->bind('test_service', function () {
        return new stdClass();
    });
    
    // 预热应用
    $manager->warm();
    
    // 验证服务已被实例化
    expect($app->has('test_service'))->toBeTrue();
});

it('can flush application', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);

    // 配置清理服务
    $this->mockConfig([
        'flush' => ['test_service'],
        'server' => 'swoole',
        'host' => '127.0.0.1',
        'port' => 8000,
    ]);

    // 注册测试服务
    $app->bind('test_service', function () {
        return new stdClass();
    });

    // 实例化服务
    $service = $app->make('test_service');
    expect($service)->toBeInstanceOf(stdClass::class);

    // 清理应用应该不会抛出异常
    expect(function () use ($manager) {
        $manager->flush();
    })->not->toThrow(\Exception::class);
});

it('resets debug state in debug mode during flush', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);

    // 设置一些调试状态
    $oldTime = microtime(true) - 100;
    $_SERVER['REQUEST_TIME_FLOAT'] = $oldTime;
    $GLOBALS['_think_start_time'] = $oldTime;
    $GLOBALS['_trace_tabs'] = ['test' => 'data'];

    // 执行清理
    $manager->flush();

    // 在调试模式下，状态应该被重置
    if (defined('APP_DEBUG') && APP_DEBUG) {
        expect($_SERVER['REQUEST_TIME_FLOAT'])->toBeGreaterThan($oldTime);
        expect($GLOBALS['_think_start_time'])->toBeGreaterThan($oldTime);
        expect(isset($GLOBALS['_trace_tabs']))->toBeFalse();
    }
});

it('resets debug state during request handling', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);

    // 创建测试请求
    $request = $this->createMockRequest();

    // 设置旧的时间状态
    $oldTime = microtime(true) - 50;
    $_SERVER['REQUEST_TIME_FLOAT'] = $oldTime;
    $GLOBALS['_think_start_time'] = $oldTime;

    // 处理请求
    $response = $manager->handle($request);

    // 验证响应
    expect($response)->toBeInstanceOf(\think\Response::class);

    // 在调试模式下，时间应该被重置
    if (defined('APP_DEBUG') && APP_DEBUG) {
        expect($_SERVER['REQUEST_TIME_FLOAT'])->toBeGreaterThan($oldTime);
        expect($GLOBALS['_think_start_time'])->toBeGreaterThan($oldTime);
    }
});

it('can handle exceptions in request processing', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);

    // 创建模拟请求
    $request = $this->createMockRequest();

    // 模拟HTTP服务抛出异常
    $app->http = new class {
        public function run($request) {
            throw new \Exception('Test exception');
        }
    };

    $response = $manager->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getCode())->toBe(500);
    expect($manager->getRequestCount())->toBe(1);
});

it('can handle http exceptions with status codes', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);

    // 创建模拟请求
    $request = $this->createMockRequest();

    // 模拟HTTP服务抛出HTTP异常
    $app->http = new class {
        public function run($request) {
            throw new HttpException(404, 'Not Found');
        }
    };

    $response = $manager->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getCode())->toBe(404);
});

it('should restart when max requests reached', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);

    // 设置较小的最大请求数
    $manager->setMaxRequests(2);

    // 模拟HTTP服务
    $app->http = new class {
        public function run($request) {
            return Response::create('OK');
        }
    };

    $request = $this->createMockRequest();

    // 处理第一个请求
    $manager->handle($request);
    expect($manager->getRequestCount())->toBe(1);

    // 处理第二个请求（达到最大值）
    $manager->handle($request);
    expect($manager->getRequestCount())->toBe(0); // 应该重置
});

it('can check if should restart', function () {
    $app = $this->getApp();
    $manager = new ApplicationManager($app);

    $manager->setMaxRequests(3);

    // 使用反射访问私有方法
    $shouldRestart = $this->callPrivateMethod($manager, 'shouldRestart');
    expect($shouldRestart)->toBeFalse();

    // 设置请求计数到最大值
    $this->setPrivateProperty($manager, 'requestCount', 3);
    $shouldRestart = $this->callPrivateMethod($manager, 'shouldRestart');
    expect($shouldRestart)->toBeTrue();
});
