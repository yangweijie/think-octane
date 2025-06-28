<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use think\App;
use think\Config;
use think\Container;
use think\Request;
use think\Response;
use ReflectionClass;

/**
 * 测试基类
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * ThinkPHP应用实例
     */
    protected ?App $app = null;

    /**
     * 设置测试环境
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 确保调试模式启用
        $this->ensureDebugMode();

        $this->createApplication();
    }

    /**
     * 确保调试模式启用
     */
    protected function ensureDebugMode(): void
    {
        // 设置调试模式常量
        if (!defined('APP_DEBUG')) {
            define('APP_DEBUG', true);
        }

        // 设置环境变量
        $_ENV['APP_DEBUG'] = true;
        $_SERVER['APP_DEBUG'] = true;

        // 设置开始时间
        if (!defined('THINK_START_TIME')) {
            define('THINK_START_TIME', microtime(true));
        }

        // 设置请求时间
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
        $_SERVER['REQUEST_TIME'] = time();
    }

    /**
     * 清理测试环境
     */
    protected function tearDown(): void
    {
        // 清理临时文件
        $this->cleanupTempFiles();

        // 清理Mockery（如果可用）
        if (class_exists('Mockery')) {
            \Mockery::close();
        }

        $this->app = null;
        Container::setInstance(null);

        parent::tearDown();
    }

    /**
     * 临时文件列表
     */
    protected array $tempFiles = [];

    /**
     * 创建应用实例
     */
    protected function createApplication(): void
    {
        $this->app = new App();
        
        // 设置基础配置
        $this->app->config->set([
            'log' => [
                'default' => 'file',
                'channels' => [
                    'file' => [
                        'type' => 'file',
                        'path' => sys_get_temp_dir() . '/test.log',
                    ],
                ],
            ],
            'octane' => [
                'server' => 'swoole',
                'host' => '127.0.0.1',
                'port' => 8000,
                'workers' => 4,
                'max_requests' => 500,
                'swoole' => [
                    'options' => [
                        'worker_num' => 4,
                        'task_worker_num' => 0,
                        'max_request' => 500,
                    ],
                ],
                'workerman' => [
                    'worker_num' => 4,
                    'max_requests' => 500,
                ],
                'reactphp' => [
                    'worker_num' => 4,
                    'max_requests' => 500,
                ],
                'flush' => ['cache', 'session'],
                'warm' => [],
                'garbage_collection' => [
                    'enabled' => true,
                    'probability' => 50,
                    'cycles' => 1000,
                ],
            ],
        ]);
        
        // 设置容器实例
        Container::setInstance($this->app);
    }

    /**
     * 获取应用实例
     */
    protected function getApp(): App
    {
        return $this->app;
    }

    /**
     * 模拟配置
     */
    protected function mockConfig(array $config): void
    {
        $this->app->config->set($config, 'octane');
    }

    /**
     * 创建临时文件
     */
    protected function createTempFile(string $content = ''): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'octane_test_');
        file_put_contents($tempFile, $content);
        $this->tempFiles[] = $tempFile;

        return $tempFile;
    }

    /**
     * 删除临时文件
     */
    protected function deleteTempFile(string $file): void
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * 清理所有临时文件
     */
    protected function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            $this->deleteTempFile($file);
        }
        $this->tempFiles = [];
    }

    /**
     * 创建模拟请求
     */
    protected function createMockRequest(array $data = []): Request
    {
        // 创建一个简单的Request实例而不是使用Mockery
        return $this->app->make(Request::class, [
            'get' => $data['get'] ?? [],
            'post' => $data['post'] ?? [],
            'files' => $data['files'] ?? [],
            'cookie' => $data['cookie'] ?? [],
            'server' => array_merge([
                'REQUEST_METHOD' => $data['method'] ?? 'GET',
                'REQUEST_URI' => $data['uri'] ?? '/',
                'HTTP_HOST' => 'localhost',
            ], $data['server'] ?? []),
        ]);
    }

    /**
     * 创建模拟响应
     */
    protected function createMockResponse(string $content = 'test', int $code = 200): Response
    {
        return Response::create($content, 'html', $code);
    }

    /**
     * 获取对象的私有属性
     */
    protected function getPrivateProperty(object $object, string $property)
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($object);
    }

    /**
     * 设置对象的私有属性
     */
    protected function setPrivateProperty(object $object, string $property, $value): void
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    /**
     * 调用对象的私有方法
     */
    protected function callPrivateMethod(object $object, string $method, array $args = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
