<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Support;

use think\App;

/**
 * ThinkPHP 状态重置器
 */
class ThinkPHPResetter
{
    /**
     * 重置 ThinkPHP 应用状态
     */
    public static function resetApp(App $app): void
    {
        // 重置运行时间
        self::resetTimer($app);
        
        // 重置内存统计
        self::resetMemory($app);
        
        // 重置调试状态
        self::resetDebugState($app);
        
        // 重置请求状态
        self::resetRequestState($app);
    }

    /**
     * 重置运行时间计算
     */
    public static function resetTimer(App $app): void
    {
        $currentTime = microtime(true);
        
        // 重置 $_SERVER 中的请求时间
        $_SERVER['REQUEST_TIME_FLOAT'] = $currentTime;
        $_SERVER['REQUEST_TIME'] = (int) $currentTime;
        
        // 重置应用的开始时间
        self::setAppProperty($app, 'beginTime', $currentTime);
        
        // 重置全局开始时间变量
        if (defined('THINK_START_TIME')) {
            // 无法重新定义常量，但可以通过其他方式重置
            $GLOBALS['_octane_start_time'] = $currentTime;
        }
        
        // 重置可能的其他时间变量
        $GLOBALS['_think_start_time'] = $currentTime;
        $GLOBALS['_app_start_time'] = $currentTime;
    }

    /**
     * 重置内存统计
     */
    public static function resetMemory(App $app): void
    {
        $currentMemory = memory_get_usage();
        
        // 重置应用的开始内存
        self::setAppProperty($app, 'beginMem', $currentMemory);
        
        // 重置全局内存变量
        $GLOBALS['_think_start_mem'] = $currentMemory;
        $GLOBALS['_app_start_mem'] = $currentMemory;
    }

    /**
     * 重置调试状态
     */
    public static function resetDebugState(App $app): void
    {
        // 重置 think-trace 相关状态
        self::resetThinkTrace($app);
        
        // 重置调试计数器
        self::resetDebugCounters();
        
        // 重置日志状态
        self::resetLogState($app);
    }

    /**
     * 重置请求状态
     */
    public static function resetRequestState(App $app): void
    {
        // 重置请求相关的全局变量
        unset($GLOBALS['_think_request_id']);
        unset($GLOBALS['_think_response_time']);
        
        // 重置可能的缓存状态
        if (isset($GLOBALS['_think_cache_stats'])) {
            $GLOBALS['_think_cache_stats'] = [
                'reads' => 0,
                'writes' => 0,
            ];
        }
        
        // 重置数据库查询统计
        if (isset($GLOBALS['_think_db_queries'])) {
            $GLOBALS['_think_db_queries'] = 0;
        }
    }

    /**
     * 重置 think-trace 状态
     */
    protected static function resetThinkTrace(App $app): void
    {
        // 清理 trace 相关的全局变量
        unset($GLOBALS['_trace_tabs']);
        unset($GLOBALS['_trace_data']);
        unset($GLOBALS['_trace_info']);
        
        // 如果存在 trace 服务，尝试重置
        if ($app->has('trace')) {
            try {
                $trace = $app->make('trace');
                
                // 尝试调用重置方法
                if (method_exists($trace, 'reset')) {
                    $trace->reset();
                }
                
                // 重置 trace 的私有属性
                self::resetTraceProperties($trace);
                
            } catch (\Throwable $e) {
                // 忽略错误
            }
        }
    }

    /**
     * 重置 trace 对象的属性
     */
    protected static function resetTraceProperties($trace): void
    {
        try {
            $reflection = new \ReflectionClass($trace);
            
            // 重置可能的时间属性
            $timeProperties = ['beginTime', 'startTime', 'time'];
            foreach ($timeProperties as $prop) {
                if ($reflection->hasProperty($prop)) {
                    $property = $reflection->getProperty($prop);
                    $property->setAccessible(true);
                    $property->setValue($trace, microtime(true));
                }
            }
            
            // 重置可能的内存属性
            $memProperties = ['beginMem', 'startMem', 'memory'];
            foreach ($memProperties as $prop) {
                if ($reflection->hasProperty($prop)) {
                    $property = $reflection->getProperty($prop);
                    $property->setAccessible(true);
                    $property->setValue($trace, memory_get_usage());
                }
            }
            
            // 重置可能的数据属性
            $dataProperties = ['data', 'tabs', 'info'];
            foreach ($dataProperties as $prop) {
                if ($reflection->hasProperty($prop)) {
                    $property = $reflection->getProperty($prop);
                    $property->setAccessible(true);
                    $property->setValue($trace, []);
                }
            }
            
        } catch (\Throwable $e) {
            // 忽略反射错误
        }
    }

    /**
     * 重置调试计数器
     */
    protected static function resetDebugCounters(): void
    {
        // 重置各种计数器
        $GLOBALS['_debug_query_count'] = 0;
        $GLOBALS['_debug_cache_count'] = 0;
        $GLOBALS['_debug_file_count'] = 0;
    }

    /**
     * 重置日志状态
     */
    protected static function resetLogState(App $app): void
    {
        // 如果存在日志服务，重置其状态
        if ($app->has('log')) {
            try {
                $log = $app->make('log');
                
                // 重置日志的内部状态
                if (method_exists($log, 'clear')) {
                    $log->clear();
                }
                
            } catch (\Throwable $e) {
                // 忽略错误
            }
        }
    }

    /**
     * 设置应用属性
     */
    protected static function setAppProperty(App $app, string $property, $value): void
    {
        try {
            $reflection = new \ReflectionClass($app);
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setAccessible(true);
                $prop->setValue($app, $value);
            }
        } catch (\Throwable $e) {
            // 忽略反射错误
        }
    }

    /**
     * 获取重置统计信息
     */
    public static function getResetStats(): array
    {
        return [
            'reset_time' => microtime(true),
            'current_memory' => memory_get_usage(),
            'peak_memory' => memory_get_peak_usage(),
            'globals_reset' => [
                '_think_start_time' => isset($GLOBALS['_think_start_time']),
                '_think_start_mem' => isset($GLOBALS['_think_start_mem']),
                '_trace_tabs' => !isset($GLOBALS['_trace_tabs']),
                '_trace_data' => !isset($GLOBALS['_trace_data']),
            ],
        ];
    }
}
