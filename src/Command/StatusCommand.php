<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\Table;
use yangweijie\thinkOctane\Server\ServerInterface;
use yangweijie\thinkOctane\Server\SwooleServer;
use yangweijie\thinkOctane\Server\WorkermanServer;
use yangweijie\thinkOctane\Server\ReactPhpServer;

/**
 * Octane状态命令
 */
class StatusCommand extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->setName('octane:status')
             ->setDescription('Show the status of the Octane server')
             ->addArgument('server', Argument::OPTIONAL, 'The server to check (swoole, workerman, reactphp)', null);
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
            
            // 显示服务器状态
            $this->displayStatus($server, $output);
            
            return 0;
            
        } catch (\Throwable $e) {
            $output->error("Failed to get Octane server status: " . $e->getMessage());
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
     * 显示服务器状态
     */
    protected function displayStatus(ServerInterface $server, Output $output): void
    {
        $isRunning = $server->isRunning();
        $status = $server->status();
        
        $output->writeln('');
        $output->writeln('<info>Octane Server Status</info>');
        $output->writeln('');
        
        // 基本状态表格
        $table = new Table($output);
        $table->setHeader(['Property', 'Value']);
        
        $table->addRow(['Server', $server->getName()]);
        $table->addRow(['Status', $isRunning ? '<info>Running</info>' : '<error>Stopped</error>']);
        
        if ($isRunning) {
            $table->addRow(['PID File', $server->getPidFile()]);
            
            // 添加服务器特定状态
            foreach ($status as $key => $value) {
                if (is_array($value)) {
                    if ($key === 'memory_usage') {
                        $this->addMemoryRows($table, $value);
                    } else {
                        $table->addRow([ucfirst($key), json_encode($value)]);
                    }
                } else {
                    $table->addRow([ucfirst(str_replace('_', ' ', $key)), $value]);
                }
            }
        }
        
        $table->render();
        
        // 如果服务器正在运行，显示详细统计信息
        if ($isRunning && isset($status['memory_usage'])) {
            $this->displayMemoryUsage($status['memory_usage'], $output);
        }
        
        $output->writeln('');
    }

    /**
     * 添加内存使用行
     */
    protected function addMemoryRows(Table $table, array $memoryUsage): void
    {
        $table->addRow(['Memory Usage', $memoryUsage['memory_usage_formatted'] ?? 'N/A']);
        $table->addRow(['Peak Memory', $memoryUsage['memory_peak_usage_formatted'] ?? 'N/A']);
        $table->addRow(['Memory Limit', $memoryUsage['memory_limit'] ?? 'N/A']);
    }

    /**
     * 显示内存使用详情
     */
    protected function displayMemoryUsage(array $memoryUsage, Output $output): void
    {
        $output->writeln('<info>Memory Usage Details</info>');
        $output->writeln('');
        
        $table = new Table($output);
        $table->setHeader(['Metric', 'Value', 'Bytes']);
        
        $table->addRow([
            'Current Usage',
            $memoryUsage['memory_usage_formatted'] ?? 'N/A',
            number_format($memoryUsage['memory_usage'] ?? 0)
        ]);
        
        $table->addRow([
            'Peak Usage',
            $memoryUsage['memory_peak_usage_formatted'] ?? 'N/A',
            number_format($memoryUsage['memory_peak_usage'] ?? 0)
        ]);
        
        $table->addRow([
            'Memory Limit',
            $memoryUsage['memory_limit'] ?? 'N/A',
            $memoryUsage['memory_limit'] === '-1' ? 'Unlimited' : 'N/A'
        ]);
        
        $table->render();
    }
}
