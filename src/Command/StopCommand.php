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
use yangweijie\thinkOctane\Support\Platform;

/**
 * Octane停止命令
 */
class StopCommand extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->setName('octane:stop')
             ->setDescription('Stop the Octane server')
             ->addArgument('server', Argument::OPTIONAL, 'The server to stop (swoole, workerman, reactphp)', null);
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
                $output->warning("Octane server is not running.");
                return 0;
            }
            
            $output->writeln("<info>Stopping Octane {$serverType} server...</info>");

            // 停止服务器
            $this->stopServer($server, $output);

            // 额外检查：根据端口清理残留进程
            $this->cleanupByPort($server, $output);

            $output->writeln("<info>Octane server stopped successfully.</info>");
            
            return 0;
            
        } catch (\Throwable $e) {
            $output->error("Failed to stop Octane server: " . $e->getMessage());
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
     * 停止服务器
     */
    protected function stopServer(ServerInterface $server, Output $output): void
    {
        // 尝试优雅停止
        try {
            $server->stop();
        } catch (\Throwable $e) {
            $output->warning("Graceful shutdown failed, trying force stop...");
            $this->forceStop($server, $output);
        }
    }

    /**
     * 强制停止服务器
     */
    protected function forceStop(ServerInterface $server, Output $output): void
    {
        $pidFile = $server->getPidFile();

        if (!file_exists($pidFile)) {
            $output->warning("PID file not found: {$pidFile}");
            return;
        }

        $pidContent = file_get_contents($pidFile);

        // Workerman 的 PID 文件可能包含多个 PID
        $pids = array_filter(array_map('intval', explode("\n", $pidContent)));

        if (empty($pids)) {
            $output->warning("No valid PIDs found in file: {$pidFile}");
            return;
        }

        $output->writeln("Found PIDs: " . implode(', ', $pids));

        // 终止所有相关进程
        foreach ($pids as $pid) {
            if ($pid > 0 && Platform::processExists($pid)) {
                $output->writeln("Terminating process: {$pid}");

                // 先尝试优雅停止（包括子进程）
                if (!Platform::killProcessTree($pid)) {
                    // 如果优雅停止失败，强制终止
                    $output->warning("Graceful shutdown failed for PID {$pid}, forcing termination...");
                    Platform::killProcessTree($pid, true);
                }

                // 等待进程结束
                $timeout = 5;
                while ($timeout > 0 && Platform::processExists($pid)) {
                    sleep(1);
                    $timeout--;
                }

                if (Platform::processExists($pid)) {
                    $output->error("Failed to terminate process: {$pid}");
                } else {
                    $output->writeln("Process {$pid} terminated successfully");
                }
            }
        }

        // 删除PID文件
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }

        // 额外检查：根据端口清理残留进程
        $this->cleanupByPort($server, $output);
    }

    /**
     * 根据端口清理残留进程
     */
    protected function cleanupByPort(ServerInterface $server, Output $output): void
    {
        $config = $server->getConfig();
        $port = $config['port'] ?? 8000;

        $output->writeln("Checking for processes on port {$port}...");

        $killedPids = Platform::killProcessByPort($port, true);

        if (!empty($killedPids)) {
            $output->writeln("Cleaned up additional processes: " . implode(', ', $killedPids));
        } else {
            $output->writeln("No additional processes found on port {$port}");
        }
    }
}
