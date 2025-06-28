<?php

declare(strict_types=1);

namespace Yangweijie\ThinkOctane;

use think\Service;
use Yangweijie\ThinkOctane\Command\StartCommand;
use Yangweijie\ThinkOctane\Command\StopCommand;
use Yangweijie\ThinkOctane\Command\ReloadCommand;
use Yangweijie\ThinkOctane\Command\StatusCommand;
use Yangweijie\ThinkOctane\Manager\ApplicationManager;
use Yangweijie\ThinkOctane\Manager\MemoryManager;

/**
 * Octane服务提供者
 * 
 * 负责注册Octane相关服务和命令
 */
class OctaneServiceProvider extends Service
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册应用管理器
        $this->app->bind('octane.application.manager', function () {
            return new ApplicationManager($this->app);
        });

        // 注册内存管理器
        $this->app->bind('octane.memory.manager', function () {
            return new MemoryManager();
        });

        // 合并配置
        $this->mergeConfigFrom(
            __DIR__ . '/../config/octane.php',
            'octane'
        );
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 注册命令
        $this->commands([
            StartCommand::class,
            StopCommand::class,
            ReloadCommand::class,
            StatusCommand::class,
        ]);

        // 发布配置文件
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/octane.php' => $this->app->getConfigPath() . 'octane.php',
            ], 'octane-config');
        }
    }

    /**
     * 合并配置文件
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        if (!$this->app->configExists($key)) {
            $config = require $path;
            $this->app->config->set($config, $key);
        }
    }

    /**
     * 发布文件
     */
    protected function publishes(array $paths, string $group = null): void
    {
        // ThinkPHP的发布机制实现
        foreach ($paths as $from => $to) {
            if (file_exists($from) && !file_exists($to)) {
                $dir = dirname($to);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                copy($from, $to);
            }
        }
    }
}
