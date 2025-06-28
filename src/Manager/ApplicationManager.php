<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Manager;

use think\App;
use think\Request;
use think\Response;
use yangweijie\thinkOctane\Support\DebugHelper;
use yangweijie\thinkOctane\Support\ThinkPHPResetter;

/**
 * 应用管理器
 * 
 * 负责管理ThinkPHP应用的生命周期
 */
class ApplicationManager
{
    /**
     * ThinkPHP应用实例
     */
    protected App $app;

    /**
     * 请求计数器
     */
    protected int $requestCount = 0;

    /**
     * 最大请求数
     */
    protected int $maxRequests;

    /**
     * 构造函数
     */
    public function __construct(App $app)
    {
        $this->app = $app;

        // 尝试从应用配置或全局配置获取最大请求数
        if ($app->config && $app->config->has('octane.max_requests')) {
            $this->maxRequests = (int) $app->config->get('octane.max_requests', 500);
        } elseif (function_exists('config')) {
            $this->maxRequests = (int) config('octane.max_requests', 500);
        } else {
            $this->maxRequests = 500;
        }
    }

    /**
     * 处理请求
     */
    public function handle(Request $request): Response
    {
        // 在调试模式下，每次请求开始时重置状态
        if (DebugHelper::isDebugMode()) {
            ThinkPHPResetter::resetApp($this->app);
        }

        // 增加请求计数
        $this->requestCount++;

        // 设置当前请求
        $this->app->instance('request', $request);

        try {
            // 执行应用
            $response = $this->app->http->run($request);

            // 检查是否需要重启worker
            if ($this->shouldRestart()) {
                $this->restart();
            }

            return $response;

        } catch (\Throwable $e) {
            // 处理异常
            return $this->handleException($e);
        }
    }

    /**
     * 预热应用
     */
    public function warm(): void
    {
        // 预热配置的服务
        $warmServices = $this->getConfig('warm', []);

        foreach ($warmServices as $service) {
            if (is_string($service) && $this->app->has($service)) {
                $this->app->make($service);
            }
        }
    }

    /**
     * 刷新应用状态
     */
    public function flush(): void
    {
        // 清理配置的服务
        $flushServices = $this->getConfig('flush', []);

        foreach ($flushServices as $service) {
            if (is_string($service) && $this->app->has($service)) {
                $this->app->delete($service);
            }
        }

        // 清理请求相关的单例
        $this->app->delete('request');
        $this->app->delete('response');

        // 清理更多可能的服务
        $this->clearCommonServices();

        // 重置应用状态
        $this->resetApplicationState();

        // 重置调试状态（在调试模式下）
        $this->resetDebugState();
    }

    /**
     * 清理常见服务
     */
    protected function clearCommonServices(): void
    {
        $commonServices = [
            'session',
            'cookie',
            'view',
            'template',
            'cache',
            'db',
            'validate',
            'filesystem',
        ];

        // 获取调试相关的服务（不应该被清理）
        $debugServices = DebugHelper::getDebugServices();

        // 在非调试模式下添加更多可清理的服务
        if (!DebugHelper::isDebugMode()) {
            $commonServices = array_merge($commonServices, ['log', 'middleware']);
        }

        // 过滤掉调试相关的服务
        $commonServices = array_diff($commonServices, $debugServices);

        foreach ($commonServices as $service) {
            if ($this->app->has($service)) {
                try {
                    $this->app->delete($service);
                } catch (\Throwable $e) {
                    // 忽略删除错误
                }
            }
        }
    }

    /**
     * 重置应用状态
     */
    protected function resetApplicationState(): void
    {
        $isDebugMode = DebugHelper::isDebugMode();

        // 在非调试模式下才重置错误和异常处理器
        if (!$isDebugMode && method_exists($this->app, 'resetErrorHandler')) {
            $this->app->resetErrorHandler();
        }

        // 在非调试模式下才清理中间件栈
        if (!$isDebugMode && method_exists($this->app, 'clearMiddleware')) {
            $this->app->clearMiddleware();
        }

        // 在非调试模式下才重置路由
        if (!$isDebugMode && $this->app->has('route')) {
            try {
                $this->app->delete('route');
            } catch (\Throwable $e) {
                // 忽略错误
            }
        }
    }

    /**
     * 处理异常
     */
    protected function handleException(\Throwable $e): Response
    {
        // 记录异常
        $this->app->log->error('Application Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        // 创建错误响应
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        
        return Response::create([
            'error' => 'Application Error',
            'message' => $this->app->isDebug() ? $e->getMessage() : 'Internal Server Error',
            'code' => $statusCode,
        ], 'json', $statusCode);
    }

    /**
     * 检查是否应该重启worker
     */
    protected function shouldRestart(): bool
    {
        return $this->requestCount >= $this->maxRequests;
    }

    /**
     * 重启worker
     */
    protected function restart(): void
    {
        // 重置请求计数
        $this->requestCount = 0;

        // 触发重启事件
        if (function_exists('swoole_server') && swoole_server()) {
            swoole_server()->reload();
        }
    }

    /**
     * 获取请求计数
     */
    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    /**
     * 获取最大请求数
     */
    public function getMaxRequests(): int
    {
        return $this->maxRequests;
    }

    /**
     * 设置最大请求数
     */
    public function setMaxRequests(int $maxRequests): void
    {
        $this->maxRequests = $maxRequests;
    }

    /**
     * 重置请求计数
     */
    public function resetRequestCount(): void
    {
        $this->requestCount = 0;
    }

    /**
     * 获取配置值
     */
    protected function getConfig(string $key, $default = null)
    {
        $fullKey = 'octane.' . $key;

        if ($this->app->config && $this->app->config->has($fullKey)) {
            return $this->app->config->get($fullKey, $default);
        } elseif (function_exists('config')) {
            return config($fullKey, $default);
        }

        return $default;
    }

    /**
     * 重置调试状态
     */
    protected function resetDebugState(): void
    {
        if (!DebugHelper::isDebugMode()) {
            return;
        }

        // 使用专门的重置工具
        ThinkPHPResetter::resetApp($this->app);
    }


}
