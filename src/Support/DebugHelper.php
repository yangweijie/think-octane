<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Support;

/**
 * 调试模式辅助工具
 */
class DebugHelper
{
    /**
     * 检查是否为调试模式
     */
    public static function isDebugMode(): bool
    {
        // 检查多种调试模式标识
        return defined('APP_DEBUG') && APP_DEBUG ||
               (function_exists('env') && env('APP_DEBUG', false)) ||
               (function_exists('config') && config('app.debug', false)) ||
               $_ENV['APP_DEBUG'] ?? false ||
               self::hasDebugPackages();
    }

    /**
     * 检查是否安装了调试相关的包
     */
    public static function hasDebugPackages(): bool
    {
        return class_exists('think\\trace\\TraceDebug') ||
               class_exists('think\\debug\\Console') ||
               self::hasThinkTrace();
    }

    /**
     * 检查是否应该保留调试输出
     */
    public static function shouldPreserveDebugOutput(): bool
    {
        return self::isDebugMode() || self::hasThinkTrace();
    }

    /**
     * 检查是否有 think-trace
     */
    public static function hasThinkTrace(): bool
    {
        // 检查类是否存在
        if (class_exists('think\\trace\\TraceDebug')) {
            return true;
        }

        // 检查多个可能的路径
        $possiblePaths = [
            getcwd() . '/vendor/topthink/think-trace',
            dirname(getcwd()) . '/tp-workerman-test/vendor/topthink/think-trace',
            __DIR__ . '/../../vendor/topthink/think-trace',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取调试相关的服务列表（不应该被清理）
     */
    public static function getDebugServices(): array
    {
        $services = [];
        
        if (self::isDebugMode()) {
            $services = array_merge($services, [
                'log',
                'trace',
                'debug',
                'middleware',
            ]);
        }
        
        return $services;
    }

    /**
     * 检查是否应该清理输出缓冲区
     */
    public static function shouldClearOutputBuffer(): bool
    {
        // 如果是调试模式或有 think-trace，不清理输出缓冲区
        return !self::shouldPreserveDebugOutput();
    }

    /**
     * 安全地清理输出缓冲区（保留调试输出）
     */
    public static function safeCleanOutputBuffer(): void
    {
        if (self::shouldClearOutputBuffer()) {
            while (ob_get_level()) {
                ob_end_clean();
            }
        }
    }

    /**
     * 获取调试信息
     */
    public static function getDebugInfo(): array
    {
        return [
            'debug_mode' => self::isDebugMode(),
            'has_think_trace' => self::hasThinkTrace(),
            'has_debug_packages' => self::hasDebugPackages(),
            'should_preserve_output' => self::shouldPreserveDebugOutput(),
            'debug_services' => self::getDebugServices(),
            'constants' => [
                'APP_DEBUG' => defined('APP_DEBUG') ? APP_DEBUG : 'undefined',
            ],
            'env_vars' => [
                'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? 'undefined',
            ],
        ];
    }
}
