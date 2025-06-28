<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;
use yangweijie\thinkOctane\Server\ServerInterface;
use yangweijie\thinkOctane\Server\SwooleServer;
use yangweijie\thinkOctane\Server\WorkermanServer;
use yangweijie\thinkOctane\Server\ReactPhpServer;

/**
 * Octane启动命令
 */
class StartCommand extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->setName('octane:start')
             ->setDescription('Start the Octane server')
             ->addArgument('server', Argument::OPTIONAL, 'The server to use (swoole, workerman, reactphp)', null)
             ->addOption('host', null, Option::VALUE_OPTIONAL, 'The host to bind to', '127.0.0.1')
             ->addOption('port', null, Option::VALUE_OPTIONAL, 'The port to bind to', 8000)
             ->addOption('workers', null, Option::VALUE_OPTIONAL, 'The number of workers to start', null)
             ->addOption('max-requests', null, Option::VALUE_OPTIONAL, 'The maximum number of requests per worker', null)
             ->addOption('watch', null, Option::VALUE_NONE, 'Enable file watching for development')
             ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the server in daemon mode');
    }

    /**
     * 执行命令
     */
    protected function execute(Input $input, Output $output): int
    {
        try {
            // 获取服务器类型
            $serverType = $input->getArgument('server') ?: $this->app->config->get('octane.server', 'swoole');
            
            // 获取配置
            $host = $input->getOption('host') ?: $this->app->config->get('octane.host', '127.0.0.1');
            $port = (int) ($input->getOption('port') ?: $this->app->config->get('octane.port', 8000));
            
            // 创建服务器实例
            $server = $this->createServer($serverType);

            // Windows 兼容性提示
            if (PHP_OS_FAMILY === 'Windows' && $serverType === 'workerman') {
                $output->warning("Workerman on Windows may have limited functionality. Consider using Swoole or ReactPHP for better Windows support.");
            }

            // 检查服务器是否已经在运行
            if ($server->isRunning()) {
                $output->error("Octane server is already running.");
                return 1;
            }
            
            // 应用命令行选项
            $this->applyOptions($server, $input);

            // 显示启动信息
            $this->displayStartInfo($output, $serverType, $host, $port);

            // 启动服务器
            $server->start($host, $port);
            
            return 0;
            
        } catch (\Throwable $e) {
            $output->error("Failed to start Octane server: " . $e->getMessage());
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
     * 应用命令行选项
     */
    protected function applyOptions(ServerInterface $server, Input $input): void
    {
        $config = [];
        
        // 工作进程数
        if ($workers = $input->getOption('workers')) {
            $config['workers'] = (int) $workers;
        }
        
        // 最大请求数
        if ($maxRequests = $input->getOption('max-requests')) {
            $config['max_requests'] = (int) $maxRequests;
        }
        
        // 文件监控
        if ($input->getOption('watch')) {
            $config['watch'] = ['enabled' => true];
        }
        
        // 守护进程模式
        if ($input->getOption('daemon')) {
            $config['daemon'] = true;
        }
        
        if (!empty($config)) {
            $server->setConfig($config);
        }
    }

    /**
     * 显示启动信息
     */
    protected function displayStartInfo(Output $output, string $serverType, string $host, int $port): void
    {
        $output->writeln('');
        $output->writeln('<info>Starting Octane server...</info>');
        $output->writeln('');
        $output->writeln("  <comment>Server:</comment>    {$serverType}");
        $output->writeln("  <comment>Host:</comment>      {$host}");
        $output->writeln("  <comment>Port:</comment>      {$port}");
        $output->writeln("  <comment>URL:</comment>       http://{$host}:{$port}");
        $output->writeln('');
        $output->writeln('<info>Press Ctrl+C to stop the server</info>');
        $output->writeln('');
    }
}
