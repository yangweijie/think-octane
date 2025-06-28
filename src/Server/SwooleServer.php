<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Server;

use Swoole\Http\Server;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use think\Request;
use think\Response;

/**
 * Swoole服务器适配器
 */
class SwooleServer extends AbstractServer
{
    /**
     * Swoole HTTP服务器实例
     */
    protected ?Server $server = null;

    /**
     * 启动服务器
     */
    public function start(string $host, int $port): void
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is not installed.');
        }

        $this->server = new Server($host, $port);
        
        // 设置服务器配置
        $this->server->set($this->getSwooleConfig());
        
        // 注册事件回调
        $this->registerCallbacks();
        
        // 保存PID
        $this->savePid($this->getCurrentPid());
        
        // 启动服务器
        $this->running = true;
        $this->server->start();
    }

    /**
     * 停止服务器
     */
    public function stop(): void
    {
        if ($this->server) {
            $this->server->shutdown();
            $this->running = false;
            $this->removePidFile();
        }
    }

    /**
     * 重载服务器
     */
    public function reload(): void
    {
        if ($this->server) {
            $this->server->reload();
        }
    }

    /**
     * 获取服务器状态
     */
    public function status(): array
    {
        $stats = $this->server ? $this->server->stats() : [];
        
        return array_merge($stats, [
            'server' => $this->getName(),
            'running' => $this->isRunning(),
            'memory_usage' => $this->memoryManager->getMemoryUsage(),
        ]);
    }

    /**
     * 获取服务器名称
     */
    public function getName(): string
    {
        return 'swoole';
    }

    /**
     * 获取Swoole配置
     */
    protected function getSwooleConfig(): array
    {
        $defaultConfig = [
            'worker_num' => 4,
            'task_worker_num' => 0,
            'max_request' => 500,
            'reload_async' => true,
            'max_wait_time' => 60,
            'enable_reuse_port' => true,
            'enable_coroutine' => true,
            'hook_flags' => SWOOLE_HOOK_ALL,
            'log_file' => $this->app->getRuntimePath() . 'swoole.log',
            'log_level' => SWOOLE_LOG_INFO,
        ];

        return array_merge($defaultConfig, $this->getConfig('swoole.options', []));
    }

    /**
     * 注册事件回调
     */
    protected function registerCallbacks(): void
    {
        // Worker启动事件
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);
        
        // Worker停止事件
        $this->server->on('WorkerStop', [$this, 'onWorkerStop']);
        
        // 请求事件
        $this->server->on('Request', [$this, 'onRequest']);
        
        // 任务事件
        if ($this->getConfig('swoole.options.task_worker_num', 0) > 0) {
            $this->server->on('Task', [$this, 'onTask']);
            $this->server->on('Finish', [$this, 'onFinish']);
        }
    }

    /**
     * Worker启动回调
     */
    public function onWorkerStart(Server $server, int $workerId): void
    {
        // 预热应用
        $this->applicationManager->warm();
        
        // 记录日志
        $this->app->log->info("Swoole worker #{$workerId} started");
    }

    /**
     * Worker停止回调
     */
    public function onWorkerStop(Server $server, int $workerId): void
    {
        $this->app->log->info("Swoole worker #{$workerId} stopped");
    }

    /**
     * 请求回调
     */
    public function onRequest(SwooleRequest $request, SwooleResponse $response): void
    {
        $this->handleRequest($request, $response);
    }

    /**
     * 任务回调
     */
    public function onTask(Server $server, int $taskId, int $srcWorkerId, $data): void
    {
        // 处理异步任务
        try {
            $result = $this->processTask($data);
            $server->finish($result);
        } catch (\Throwable $e) {
            $this->app->log->error('Task error: ' . $e->getMessage());
            $server->finish(['error' => $e->getMessage()]);
        }
    }

    /**
     * 任务完成回调
     */
    public function onFinish(Server $server, int $taskId, $data): void
    {
        // 任务完成处理
        $this->app->log->info("Task #{$taskId} finished");
    }

    /**
     * 创建ThinkPHP请求对象
     */
    protected function createThinkRequest($request): Request
    {
        // 转换Swoole请求为ThinkPHP请求
        $get = $request->get ?? [];
        $post = $request->post ?? [];
        $files = $request->files ?? [];
        $cookie = $request->cookie ?? [];
        $server = $request->server ?? [];
        $header = $request->header ?? [];

        // 构建$_SERVER数组
        $serverData = array_merge($server, [
            'REQUEST_METHOD' => $server['request_method'] ?? 'GET',
            'REQUEST_URI' => $server['request_uri'] ?? '/',
            'QUERY_STRING' => $server['query_string'] ?? '',
            'HTTP_HOST' => $header['host'] ?? 'localhost',
        ]);

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
        $response->status($thinkResponse->getCode());

        // 设置响应头
        foreach ($thinkResponse->getHeader() as $name => $value) {
            $response->header($name, $value);
        }

        // 发送响应内容
        $response->end($thinkResponse->getContent());
    }

    /**
     * 处理任务
     */
    protected function processTask($data)
    {
        // 子类可以重写此方法来处理具体的任务
        return $data;
    }
}
