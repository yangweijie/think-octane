<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Server;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Protocols\Http\Response as WorkermanResponse;
use think\Request;
use think\Response;
use yangweijie\thinkOctane\Support\Platform;

/**
 * Workerman服务器适配器
 */
class WorkermanServer extends AbstractServer
{
    /**
     * Workerman Worker实例
     */
    protected ?Worker $worker = null;

    /**
     * 启动服务器
     */
    public function start(string $host, int $port): void
    {
        if (!class_exists('Workerman\Worker')) {
            throw new \RuntimeException('Workerman is not installed. Please install it via: composer require workerman/workerman');
        }

        // Windows 兼容性检查
        if (PHP_OS_FAMILY === 'Windows') {
            $this->app->log->warning('Workerman on Windows may have limited functionality. Consider using Swoole or ReactPHP for better Windows support.');
        }

        // 创建HTTP Worker
        $this->worker = new Worker("http://{$host}:{$port}");

        // 设置Worker属性
        $this->configureWorker();

        // 注册事件回调
        $this->registerCallbacks();

        // 启动服务器
        $this->running = true;

        // 在启动前保存主进程PID
        $this->savePid($this->getCurrentPid());

        Worker::runAll();
    }

    /**
     * 停止服务器
     */
    public function stop(): void
    {
        if ($this->worker) {
            Worker::stopAll();
            $this->running = false;
            $this->removePidFile();
        }
    }

    /**
     * 重载服务器
     */
    public function reload(): void
    {
        if ($this->worker) {
            Worker::reloadAllWorkers();
        }
    }

    /**
     * 获取服务器状态
     */
    public function status(): array
    {
        return [
            'server' => $this->getName(),
            'running' => $this->isRunning(),
            'worker_count' => $this->getConfig('workerman.worker_num', 4),
            'memory_usage' => $this->memoryManager->getMemoryUsage(),
            'connections' => $this->worker ? $this->worker->connections : [],
        ];
    }

    /**
     * 获取服务器名称
     */
    public function getName(): string
    {
        return 'workerman';
    }

    /**
     * 配置Worker
     */
    protected function configureWorker(): void
    {
        $config = $this->getConfig('workerman', []);
        
        // 设置进程数
        $this->worker->count = $config['worker_num'] ?? 4;
        
        // 设置进程名称
        $this->worker->name = 'think-octane-workerman';
        
        // 设置日志文件
        if (isset($config['log_file'])) {
            Worker::$logFile = $config['log_file'];
        }
        
        // 设置PID文件
        Worker::$pidFile = $this->getPidFile();

        // 设置状态文件
        Worker::$statusFile = Platform::getRuntimePath() . 'workerman.status';
        
        // 设置守护进程模式
        Worker::$daemonize = false;
    }

    /**
     * 注册事件回调
     */
    protected function registerCallbacks(): void
    {
        // Worker启动事件
        $this->worker->onWorkerStart = [$this, 'onWorkerStart'];
        
        // Worker停止事件
        $this->worker->onWorkerStop = [$this, 'onWorkerStop'];
        
        // 消息事件（HTTP请求）
        $this->worker->onMessage = [$this, 'onMessage'];
        
        // 连接关闭事件
        $this->worker->onClose = [$this, 'onClose'];
    }

    /**
     * Worker启动回调
     */
    public function onWorkerStart(Worker $worker): void
    {
        // 只在主 Worker 中执行一次
        if ($worker->id === 0) {
            // 预热应用
            $this->applicationManager->warm();

            // 保存主 Worker 的 PID
            $this->savePid($this->getCurrentPid());
        }

        // 记录日志
        $this->app->log->info("Workerman worker #{$worker->id} started, PID: " . $this->getCurrentPid());
    }

    /**
     * Worker停止回调
     */
    public function onWorkerStop(Worker $worker): void
    {
        $this->app->log->info("Workerman worker stopped");
    }

    /**
     * 消息回调（处理HTTP请求）
     */
    public function onMessage(TcpConnection $connection, WorkermanRequest $request): void
    {
        try {
            // 创建响应对象
            $response = new WorkermanResponse();

            // 处理请求
            $this->handleRequest($request, $response);

            // 发送响应
            $connection->send($response);

        } catch (\Throwable $e) {
            // 发送错误响应
            $errorResponse = new WorkermanResponse(500, [], 'Internal Server Error');
            $connection->send($errorResponse);

            // 记录错误
            $this->app->log->error('Workerman request error: ' . $e->getMessage());
        } finally {
            // 请求处理完成后清理内存
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

        // 检查是否需要重启 Worker
        if ($this->shouldRestartWorker()) {
            $this->restartWorker();
        }
    }

    /**
     * 检查是否需要重启 Worker
     */
    protected function shouldRestartWorker(): bool
    {
        // 检查请求数量
        if ($this->applicationManager->getRequestCount() >= $this->applicationManager->getMaxRequests()) {
            return true;
        }

        // 检查内存使用
        if ($this->memoryManager->isMemoryLimitExceeded(0.8)) {
            return true;
        }

        return false;
    }

    /**
     * 重启 Worker
     */
    protected function restartWorker(): void
    {
        if ($this->worker) {
            $this->app->log->info("Restarting Workerman worker due to resource limits");

            // 重置计数器
            $this->applicationManager->resetRequestCount();
            $this->memoryManager->resetRequestCount();

            // 强制垃圾回收
            $this->memoryManager->forceGarbageCollection();

            // 在 Workerman 中，我们不能直接重启 worker，但可以清理状态
            $this->applicationManager->flush();
        }
    }

    /**
     * 连接关闭回调
     */
    public function onClose(TcpConnection $connection): void
    {
        // 连接关闭处理
    }

    /**
     * 创建ThinkPHP请求对象
     */
    protected function createThinkRequest($request): Request
    {
        // 转换Workerman请求为ThinkPHP请求
        $get = $request->get() ?? [];
        $post = $request->post() ?? [];
        $files = $request->file() ?? [];
        $cookie = $request->cookie() ?? [];
        $header = $request->header() ?? [];

        // 构建$_SERVER数组
        $serverData = [
            'REQUEST_METHOD' => $request->method(),
            'REQUEST_URI' => $request->uri(),
            'QUERY_STRING' => $request->queryString(),
            'HTTP_HOST' => $header['host'] ?? 'localhost',
            'SERVER_NAME' => $header['host'] ?? 'localhost',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
        ];

        // 添加HTTP头到$_SERVER
        foreach ($header as $key => $value) {
            $serverData['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return $this->app->make(Request::class, [
            'get' => $get,
            'post' => $post,
            'files' => $files,
            'cookie' => $cookie,
            'server' => $serverData,
        ]);
    }

    /**
     * 发送响应
     */
    protected function sendResponse($response, Response $thinkResponse): void
    {
        // 设置状态码
        $response->withStatus($thinkResponse->getCode());

        // 设置响应头
        foreach ($thinkResponse->getHeader() as $name => $value) {
            $response->withHeader($name, $value);
        }

        // 设置响应内容
        $response->withBody($thinkResponse->getContent());
    }

    /**
     * 处理请求
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
            
            // 清理内存
            $this->memoryManager->flush();
            
        } catch (\Throwable $e) {
            $this->handleException($response, $e);
        }
    }

    /**
     * 处理异常
     */
    protected function handleException($response, \Throwable $e): void
    {
        // 记录错误日志
        $this->app->log->error('Workerman Server Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        // 发送错误响应
        $response->withStatus(500);
        $response->withHeader('Content-Type', 'application/json');
        $response->withBody(json_encode([
            'error' => 'Internal Server Error',
            'message' => $this->app->isDebug() ? $e->getMessage() : 'Something went wrong',
        ]));
    }
}
