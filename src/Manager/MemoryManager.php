<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Manager;

use yangweijie\thinkOctane\Support\MemoryLeakDetector;
use yangweijie\thinkOctane\Support\DebugHelper;

/**
 * 内存管理器
 * 
 * 负责内存清理和垃圾回收
 */
class MemoryManager
{
    /**
     * 垃圾回收配置
     */
    protected array $gcConfig;

    /**
     * 请求计数器
     */
    protected int $requestCount = 0;

    /**
     * 内存泄漏检测器
     */
    protected MemoryLeakDetector $leakDetector;

    /**
     * 构造函数
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'enabled' => true,
            'probability' => 50,
            'cycles' => 1000,
        ];

        // 如果没有传入配置，尝试从全局配置获取
        if (empty($config) && function_exists('config')) {
            $config = config('octane.garbage_collection', $defaultConfig);
        } else {
            $config = array_merge($defaultConfig, $config);
        }

        $this->gcConfig = $config;
        $this->leakDetector = new MemoryLeakDetector();
    }

    /**
     * 刷新内存
     */
    public function flush(): void
    {
        // 记录请求开始
        $this->leakDetector->recordRequestStart();

        $this->requestCount++;

        // 清理全局变量
        $this->clearGlobals();

        // 执行垃圾回收
        $this->garbageCollection();

        // 清理静态变量
        $this->clearStaticVariables();

        // 检测内存泄漏并执行清理
        $this->checkAndCleanupMemoryLeak();
    }

    /**
     * 清理全局变量
     */
    protected function clearGlobals(): void
    {
        // 清理$_GET, $_POST, $_FILES, $_COOKIE, $_SESSION等
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_COOKIE = [];
        
        // 清理$_SERVER中的HTTP相关变量
        $httpKeys = array_filter(array_keys($_SERVER), function ($key) {
            return strpos($key, 'HTTP_') === 0;
        });
        
        foreach ($httpKeys as $key) {
            unset($_SERVER[$key]);
        }
    }

    /**
     * 垃圾回收
     */
    protected function garbageCollection(): void
    {
        if (!$this->gcConfig['enabled']) {
            return;
        }

        // 根据概率执行垃圾回收
        if (mt_rand(1, 100) <= $this->gcConfig['probability']) {
            gc_collect_cycles();
        }

        // 根据请求次数执行垃圾回收
        if ($this->requestCount % $this->gcConfig['cycles'] === 0) {
            gc_collect_cycles();
        }
    }

    /**
     * 清理静态变量和缓存
     */
    protected function clearStaticVariables(): void
    {
        // 使用调试辅助工具检查调试模式
        $isDebugMode = DebugHelper::isDebugMode();

        // 在非调试模式下才清理缓存
        if (!$isDebugMode && class_exists('\think\facade\Cache')) {
            try {
                \think\facade\Cache::clear();
            } catch (\Throwable $e) {
                // 忽略清理错误
            }
        }

        // 在非调试模式下才清理 OPcache 和 APCu
        if (!$isDebugMode) {
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            if (function_exists('apcu_clear_cache')) {
                apcu_clear_cache();
            }
        }

        // 强制垃圾回收
        gc_collect_cycles();

        // 使用调试辅助工具安全地清理输出缓冲区
        DebugHelper::safeCleanOutputBuffer();
    }

    /**
     * 检测并清理内存泄漏
     */
    protected function checkAndCleanupMemoryLeak(): void
    {
        $leak = $this->leakDetector->detectLeak();

        if ($leak['leak_detected']) {
            // 执行额外的清理操作
            $this->leakDetector->performCleanup();

            // 记录警告
            if (function_exists('error_log')) {
                error_log("ThinkOctane: " . $leak['message']);
            }
        }
    }

    /**
     * 获取内存泄漏检测器
     */
    public function getLeakDetector(): MemoryLeakDetector
    {
        return $this->leakDetector;
    }

    /**
     * 获取内存使用情况
     */
    public function getMemoryUsage(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak_usage' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage_formatted' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak_usage_formatted' => $this->formatBytes(memory_get_peak_usage(true)),
        ];
    }

    /**
     * 格式化字节数
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * 检查内存使用是否超过限制
     */
    public function isMemoryLimitExceeded(float $threshold = 0.8): bool
    {
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $memoryUsage = memory_get_usage(true);
        
        if ($memoryLimit === -1) {
            return false; // 无限制
        }
        
        return $memoryUsage / $memoryLimit > $threshold;
    }

    /**
     * 解析内存限制
     */
    protected function parseMemoryLimit(string $memoryLimit): int
    {
        if ($memoryLimit === '-1') {
            return -1;
        }
        
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
                break;
        }
        
        return $value;
    }

    /**
     * 强制垃圾回收
     */
    public function forceGarbageCollection(): int
    {
        return gc_collect_cycles();
    }

    /**
     * 获取垃圾回收统计信息
     */
    public function getGcStats(): array
    {
        if (function_exists('gc_status')) {
            return gc_status();
        }
        
        return [
            'runs' => 0,
            'collected' => 0,
            'threshold' => 0,
            'roots' => 0,
        ];
    }

    /**
     * 重置请求计数
     */
    public function resetRequestCount(): void
    {
        $this->requestCount = 0;
    }

    /**
     * 获取请求计数
     */
    public function getRequestCount(): int
    {
        return $this->requestCount;
    }
}
