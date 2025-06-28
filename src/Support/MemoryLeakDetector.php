<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Support;

/**
 * 内存泄漏检测器
 */
class MemoryLeakDetector
{
    /**
     * 内存使用历史
     */
    protected array $memoryHistory = [];

    /**
     * 请求计数
     */
    protected int $requestCount = 0;

    /**
     * 检测间隔
     */
    protected int $checkInterval = 10;

    /**
     * 内存增长阈值（字节）
     */
    protected int $memoryGrowthThreshold = 1024 * 1024; // 1MB

    /**
     * 记录请求开始时的内存使用
     */
    public function recordRequestStart(): void
    {
        $this->requestCount++;
        
        if ($this->requestCount % $this->checkInterval === 0) {
            $this->memoryHistory[] = [
                'request' => $this->requestCount,
                'memory' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'time' => microtime(true),
            ];
            
            // 只保留最近的记录
            if (count($this->memoryHistory) > 20) {
                array_shift($this->memoryHistory);
            }
        }
    }

    /**
     * 检测是否有内存泄漏
     */
    public function detectLeak(): array
    {
        if (count($this->memoryHistory) < 3) {
            return ['leak_detected' => false, 'message' => 'Not enough data'];
        }

        $recent = array_slice($this->memoryHistory, -3);
        $first = $recent[0];
        $last = $recent[2];

        $memoryGrowth = $last['memory'] - $first['memory'];
        $timeSpan = $last['time'] - $first['time'];
        $requestSpan = $last['request'] - $first['request'];

        $leakDetected = $memoryGrowth > $this->memoryGrowthThreshold;

        return [
            'leak_detected' => $leakDetected,
            'memory_growth' => $memoryGrowth,
            'memory_growth_formatted' => $this->formatBytes($memoryGrowth),
            'time_span' => $timeSpan,
            'request_span' => $requestSpan,
            'growth_per_request' => $requestSpan > 0 ? $memoryGrowth / $requestSpan : 0,
            'current_memory' => $last['memory'],
            'current_memory_formatted' => $this->formatBytes($last['memory']),
            'message' => $leakDetected ? 
                "Memory leak detected: {$this->formatBytes($memoryGrowth)} growth over {$requestSpan} requests" :
                'No significant memory leak detected',
        ];
    }

    /**
     * 获取内存统计信息
     */
    public function getMemoryStats(): array
    {
        if (empty($this->memoryHistory)) {
            return [];
        }

        $latest = end($this->memoryHistory);
        $oldest = reset($this->memoryHistory);

        return [
            'total_requests' => $this->requestCount,
            'tracked_requests' => count($this->memoryHistory),
            'current_memory' => $latest['memory'],
            'current_memory_formatted' => $this->formatBytes($latest['memory']),
            'total_growth' => $latest['memory'] - $oldest['memory'],
            'total_growth_formatted' => $this->formatBytes($latest['memory'] - $oldest['memory']),
            'average_per_request' => ($latest['memory'] - $oldest['memory']) / max(1, $latest['request'] - $oldest['request']),
            'history' => $this->memoryHistory,
        ];
    }

    /**
     * 清理内存历史
     */
    public function reset(): void
    {
        $this->memoryHistory = [];
        $this->requestCount = 0;
    }

    /**
     * 格式化字节数
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 建议的清理操作
     */
    public function getCleanupSuggestions(): array
    {
        $leak = $this->detectLeak();
        $suggestions = [];

        if ($leak['leak_detected']) {
            $suggestions[] = 'Force garbage collection';
            $suggestions[] = 'Clear application cache';
            $suggestions[] = 'Reset static variables';
            
            if ($leak['growth_per_request'] > 100 * 1024) { // 100KB per request
                $suggestions[] = 'Consider restarting worker process';
            }
        }

        return $suggestions;
    }

    /**
     * 执行建议的清理操作
     */
    public function performCleanup(): array
    {
        $performed = [];

        // 强制垃圾回收
        $collected = gc_collect_cycles();
        $performed[] = "Garbage collection: {$collected} cycles collected";

        // 清理输出缓冲区
        $buffers = 0;
        while (ob_get_level()) {
            ob_end_clean();
            $buffers++;
        }
        if ($buffers > 0) {
            $performed[] = "Cleared {$buffers} output buffers";
        }

        // 清理 OPcache（如果可用）
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $performed[] = 'Reset OPcache';
        }

        return $performed;
    }
}
