<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Server;

use think\App;
use think\Response;
use think\Request;
use yangweijie\thinkOctane\Manager\ApplicationManager;
use yangweijie\thinkOctane\Manager\MemoryManager;
use yangweijie\thinkOctane\Support\Platform;

/**
 * 抽象服务器类
 * 
 * 提供服务器的通用功能实现
 */
abstract class AbstractServer implements ServerInterface
{
    /**
     * ThinkPHP应用实例
     */
    protected App $app;

    /**
     * 服务器配置
     */
    protected array $config = [];

    /**
     * 应用管理器
     */
    protected ApplicationManager $applicationManager;

    /**
     * 内存管理器
     */
    protected MemoryManager $memoryManager;

    /**
     * 服务器是否正在运行
     */
    protected bool $running = false;

    /**
     * 构造函数
     */
    public function __construct(App $app, array $config = [])
    {
        $this->app = $app;
        $this->config = $config;
        $this->applicationManager = $app->make(ApplicationManager::class);
        $this->memoryManager = $app->make(MemoryManager::class);
    }

    /**
     * 处理HTTP请求
     */
    protected function handleRequest($request, $response): void
    {
        try {
            // 创建ThinkPHP请求对象
            $thinkRequest = $this->createThinkRequest($request);
            
            // 处理请求
            $thinkResponse = $this->applicationManager->handle($thinkRequest);

            // 发送响应
            $this->sendResponse($response, $thinkResponse);

        } catch (\Throwable $e) {
            $this->handleException($response, $e);
        } finally {
            // 无论成功还是失败都要清理
            $this->cleanupAfterRequest();
        }
    }

    /**
     * 请求处理后的清理工作
     */
    protected function cleanupAfterRequest(): void
    {
        // 清理应用状态
        $this->applicationManager->flush();

        // 清理内存
        $this->memoryManager->flush();

        // 强制垃圾回收（每隔一定请求数）
        if ($this->applicationManager->getRequestCount() % 10 === 0) {
            $this->memoryManager->forceGarbageCollection();
        }
    }

    /**
     * 创建ThinkPHP请求对象
     */
    protected function createThinkRequest($request): Request
    {
        // 子类实现具体的请求转换逻辑
        return $this->app->make(Request::class);
    }

    /**
     * 发送响应
     */
    protected function sendResponse($response, Response $thinkResponse): void
    {
        // 子类实现具体的响应发送逻辑
    }

    /**
     * 处理异常
     */
    protected function handleException($response, \Throwable $e): void
    {
        // 记录错误日志
        $this->app->log->error('Octane Server Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        // 发送错误响应
        $errorResponse = $this->createErrorResponse($e);
        $this->sendResponse($response, $errorResponse);
    }

    /**
     * 创建错误响应
     */
    protected function createErrorResponse(\Throwable $e): Response
    {
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        
        return Response::create([
            'error' => 'Internal Server Error',
            'message' => $this->app->isDebug() ? $e->getMessage() : 'Something went wrong',
            'code' => $statusCode,
        ], 'json', $statusCode);
    }

    /**
     * 获取PID文件路径
     */
    public function getPidFile(): string
    {
        $runtimePath = Platform::getRuntimePath();
        return $runtimePath . 'octane_' . $this->getName() . '.pid';
    }

    /**
     * 保存PID
     */
    protected function savePid(int $pid): void
    {
        $pidFile = $this->getPidFile();
        $pidDir = dirname($pidFile);

        if (!is_dir($pidDir)) {
            mkdir($pidDir, 0755, true);
        }

        file_put_contents($pidFile, $pid);
    }

    /**
     * 读取PID
     */
    protected function readPid(): ?int
    {
        $pidFile = $this->getPidFile();
        if (file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);
            return $pid > 0 ? $pid : null;
        }
        return null;
    }

    /**
     * 删除PID文件
     */
    protected function removePidFile(): void
    {
        $pidFile = $this->getPidFile();
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
    }

    /**
     * 获取当前进程ID（跨平台兼容）
     */
    protected function getCurrentPid(): int
    {
        return Platform::getCurrentPid();
    }

    /**
     * 检查进程是否存在
     */
    protected function processExists(int $pid): bool
    {
        return Platform::processExists($pid);
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取配置
     */
    public function getConfig(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? $default;
    }

    /**
     * 检查服务器是否正在运行
     */
    public function isRunning(): bool
    {
        $pid = $this->readPid();
        return $pid && $this->processExists($pid);
    }
}
