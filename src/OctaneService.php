<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane;

use think\Service;
use yangweijie\thinkOctane\Command\StartCommand;
use yangweijie\thinkOctane\Command\StopCommand;
use yangweijie\thinkOctane\Command\ReloadCommand;
use yangweijie\thinkOctane\Command\StatusCommand;
use yangweijie\thinkOctane\Command\InstallCommand;
use yangweijie\thinkOctane\Command\CheckCommand;
use yangweijie\thinkOctane\Command\MemoryCommand;
use yangweijie\thinkOctane\Command\DebugCommand;
use yangweijie\thinkOctane\Command\ResetTestCommand;
use yangweijie\thinkOctane\Manager\ApplicationManager;
use yangweijie\thinkOctane\Manager\MemoryManager;

/**
 * Octane服务类
 * 
 * 负责注册Octane相关服务和命令
 */
class OctaneService extends Service
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册应用管理器
        $this->app->bind(ApplicationManager::class, function () {
            return new ApplicationManager($this->app);
        });

        // 注册内存管理器
        $this->app->bind(MemoryManager::class, function () {
            return new MemoryManager();
        });

        // 注册Octane管理器别名
        $this->app->bind('octane.application', ApplicationManager::class);
        $this->app->bind('octane.memory', MemoryManager::class);
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 注册控制台命令
        $this->commands([
            'octane:install' => InstallCommand::class,
            'octane:check' => CheckCommand::class,
            'octane:debug' => DebugCommand::class,
            'octane:memory' => MemoryCommand::class,
            'octane:reset-test' => ResetTestCommand::class,
            'octane:start' => StartCommand::class,
            'octane:stop' => StopCommand::class,
            'octane:reload' => ReloadCommand::class,
            'octane:status' => StatusCommand::class,
        ]);
    }
}
