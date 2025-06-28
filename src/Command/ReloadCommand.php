<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use yangweijie\thinkOctane\Server\ServerInterface;
use yangweijie\thinkOctane\Server\SwooleServer;
use yangweijie\thinkOctane\Server\WorkermanServer;
use yangweijie\thinkOctane\Server\ReactPhpServer;

/**
 * Octane重载命令
 */
class ReloadCommand extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->setName('octane:reload')
             ->setDescription('Reload the Octane server')
             ->addArgument('server', Argument::OPTIONAL, 'The server to reload (swoole, workerman, reactphp)', null);
    }

    /**
     * 执行命令
     */
    protected function execute(Input $input, Output $output): int
    {
        try {
            // 获取服务器类型
            $serverType = $input->getArgument('server') ?: $this->app->config->get('octane.server', 'swoole');
            
            // 创建服务器实例
            $server = $this->createServer($serverType);
            
            // 检查服务器是否在运行
            if (!$server->isRunning()) {
                $output->error("Octane server is not running. Please start it first.");
                return 1;
            }
            
            $output->writeln("<info>Reloading Octane {$serverType} server...</info>");
            
            // 重载服务器
            $this->reloadServer($server, $serverType, $output);
            
            $output->writeln("<info>Octane server reloaded successfully.</info>");
            
            return 0;
            
        } catch (\Throwable $e) {
            $output->error("Failed to reload Octane server: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * 创建服务器实例
     */
    protected function createServer(string $serverType): ServerInterface
    {
        $config = $this->app->config->get('octane', []);
        
        switch (strtolower($serverType)) {
            case 'swoole':
                return new SwooleServer($this->app, $config);
                
            case 'workerman':
                return new WorkermanServer($this->app, $config);
                
            case 'reactphp':
                return new ReactPhpServer($this->app, $config);
                
            default:
                throw new \InvalidArgumentException("Unsupported server type: {$serverType}");
        }
    }

    /**
     * 重载服务器
     */
    protected function reloadServer(ServerInterface $server, string $serverType, Output $output): void
    {
        switch (strtolower($serverType)) {
            case 'swoole':
            case 'workerman':
                // Swoole和Workerman支持热重载
                $server->reload();
                break;
                
            case 'reactphp':
                // ReactPHP不支持热重载，需要重启
                $output->warning("ReactPHP does not support hot reload. Restarting server...");
                $this->restartServer($server, $output);
                break;
                
            default:
                throw new \InvalidArgumentException("Unsupported server type: {$serverType}");
        }
    }

    /**
     * 重启服务器
     */
    protected function restartServer(ServerInterface $server, Output $output): void
    {
        // 获取服务器配置
        $config = $server->getConfig();
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 8000;
        
        // 停止服务器
        $output->writeln("Stopping server...");
        $server->stop();
        
        // 等待一秒确保服务器完全停止
        sleep(1);
        
        // 重新启动服务器
        $output->writeln("Starting server...");
        $server->start($host, $port);
    }
}
