<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Server;

use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\ServerRequest;
use React\Socket\SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response as ReactResponse;
use think\Request;
use think\Response;

/**
 * ReactPHP服务器适配器
 */
class ReactPhpServer extends AbstractServer
{
    /**
     * ReactPHP HTTP服务器实例
     */
    protected ?HttpServer $server = null;

    /**
     * Socket服务器实例
     */
    protected ?SocketServer $socket = null;

    /**
     * 事件循环
     */
    protected $loop;

    /**
     * 启动服务器
     */
    public function start(string $host, int $port): void
    {
        if (!class_exists('React\Http\HttpServer')) {
            throw new \RuntimeException('ReactPHP HTTP is not installed. Please install it via: composer require react/http react/socket');
        }

        // 创建事件循环
        $this->loop = Loop::get();
        
        // 创建HTTP服务器
        $this->server = new HttpServer($this->loop, [$this, 'handleReactRequest']);
        
        // 创建Socket服务器
        $this->socket = new SocketServer("{$host}:{$port}", [], $this->loop);
        
        // 绑定服务器到Socket
        $this->server->listen($this->socket);
        
        // 预热应用
        $this->applicationManager->warm();
        
        // 保存PID
        $this->savePid($this->getCurrentPid());
        
        // 记录启动日志
        $this->app->log->info("ReactPHP server started on {$host}:{$port}");
        
        // 启动事件循环
        $this->running = true;
        $this->loop->run();
    }

    /**
     * 停止服务器
     */
    public function stop(): void
    {
        if ($this->socket) {
            $this->socket->close();
        }
        
        if ($this->loop) {
            $this->loop->stop();
        }
        
        $this->running = false;
        $this->removePidFile();
        
        $this->app->log->info("ReactPHP server stopped");
    }

    /**
     * 重载服务器
     */
    public function reload(): void
    {
        // ReactPHP不支持热重载，需要重启
        $this->app->log->info("ReactPHP server reload requested - restart required");
    }

    /**
     * 获取服务器状态
     */
    public function status(): array
    {
        return [
            'server' => $this->getName(),
            'running' => $this->isRunning(),
            'memory_usage' => $this->memoryManager->getMemoryUsage(),
            'connections' => $this->socket ? $this->socket->getAddress() : null,
        ];
    }

    /**
     * 获取服务器名称
     */
    public function getName(): string
    {
        return 'reactphp';
    }

    /**
     * 处理HTTP请求（ReactPHP专用）
     */
    public function handleReactRequest(ServerRequestInterface $request)
    {
        try {
            // 创建ThinkPHP请求对象
            $thinkRequest = $this->createThinkRequest($request);

            // 处理请求
            $thinkResponse = $this->applicationManager->handle($thinkRequest);

            // 创建ReactPHP响应
            $reactResponse = $this->createReactResponse($thinkResponse);

            // 清理内存
            $this->memoryManager->flush();

            return $reactResponse;

        } catch (\Throwable $e) {
            return $this->handleReactException($e);
        }
    }

    /**
     * 创建ThinkPHP请求对象
     */
    protected function createThinkRequest($request): Request
    {
        // 获取请求数据
        $method = $request->getMethod();
        $uri = $request->getUri();
        $headers = $request->getHeaders();
        $body = (string) $request->getBody();
        
        // 解析查询参数
        parse_str($uri->getQuery(), $get);
        
        // 解析POST数据
        $post = [];
        if ($method === 'POST' && $body) {
            $contentType = $request->getHeaderLine('Content-Type');
            if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                parse_str($body, $post);
            } elseif (strpos($contentType, 'application/json') !== false) {
                $post = json_decode($body, true) ?? [];
            }
        }
        
        // 构建$_SERVER数组
        $serverData = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => (string) $uri,
            'QUERY_STRING' => $uri->getQuery(),
            'HTTP_HOST' => $uri->getHost(),
            'SERVER_NAME' => $uri->getHost(),
            'SERVER_PORT' => $uri->getPort(),
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'HTTPS' => $uri->getScheme() === 'https' ? 'on' : 'off',
        ];

        // 添加HTTP头到$_SERVER
        foreach ($headers as $name => $values) {
            $serverData['HTTP_' . strtoupper(str_replace('-', '_', $name))] = implode(', ', $values);
        }

        return $this->app->make(Request::class, [
            'get' => $get,
            'post' => $post,
            'files' => [], // ReactPHP需要额外处理文件上传
            'cookie' => $this->parseCookies($request->getHeaderLine('Cookie')),
            'server' => $serverData,
        ]);
    }

    /**
     * 创建ReactPHP响应对象
     */
    protected function createReactResponse(Response $thinkResponse): ReactResponse
    {
        $headers = [];
        
        // 转换响应头
        foreach ($thinkResponse->getHeader() as $name => $value) {
            $headers[$name] = $value;
        }
        
        return new ReactResponse(
            $thinkResponse->getCode(),
            $headers,
            $thinkResponse->getContent()
        );
    }

    /**
     * 处理异常（ReactPHP专用）
     */
    protected function handleReactException(\Throwable $e): ReactResponse
    {
        // 记录错误日志
        $this->app->log->error('ReactPHP Server Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        // 创建错误响应
        $errorData = [
            'error' => 'Internal Server Error',
            'message' => $this->app->isDebug() ? $e->getMessage() : 'Something went wrong',
        ];

        return new ReactResponse(
            500,
            ['Content-Type' => 'application/json'],
            json_encode($errorData)
        );
    }

    /**
     * 解析Cookie
     */
    protected function parseCookies(string $cookieHeader): array
    {
        $cookies = [];
        
        if (empty($cookieHeader)) {
            return $cookies;
        }
        
        $pairs = explode(';', $cookieHeader);
        
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (strpos($pair, '=') !== false) {
                list($name, $value) = explode('=', $pair, 2);
                $cookies[trim($name)] = trim($value);
            }
        }
        
        return $cookies;
    }

    /**
     * 处理请求（兼容AbstractServer接口）
     */
    protected function handleRequest($request, $response): void
    {
        // ReactPHP不使用此方法，实际处理在handleReactRequest中
        throw new \RuntimeException('ReactPHP server uses handleReactRequest method instead');
    }

    /**
     * 发送响应（ReactPHP中不需要此方法，但为了兼容接口）
     */
    protected function sendResponse($response, Response $thinkResponse): void
    {
        // ReactPHP通过返回值发送响应，此方法留空
    }
}
