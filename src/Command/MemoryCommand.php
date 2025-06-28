<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\Table;
use yangweijie\thinkOctane\Manager\MemoryManager;

/**
 * 内存监控命令
 */
class MemoryCommand extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->setName('octane:memory')
             ->setDescription('Monitor memory usage and detect leaks');
    }

    /**
     * 执行命令
     */
    protected function execute(Input $input, Output $output): int
    {
        $output->writeln('');
        $output->writeln('<info>ThinkOctane Memory Monitor</info>');
        $output->writeln('');

        // 创建内存管理器
        $memoryManager = new MemoryManager();
        
        // 显示当前内存使用情况
        $this->displayCurrentMemoryUsage($output, $memoryManager);
        
        // 显示内存泄漏检测结果
        $this->displayLeakDetection($output, $memoryManager);
        
        // 显示清理建议
        $this->displayCleanupSuggestions($output, $memoryManager);

        return 0;
    }

    /**
     * 显示当前内存使用情况
     */
    protected function displayCurrentMemoryUsage(Output $output, MemoryManager $memoryManager): void
    {
        $output->writeln('<comment>Current Memory Usage:</comment>');
        $output->writeln('');

        $usage = $memoryManager->getMemoryUsage();
        $table = new Table($output);
        $table->setHeader(['Metric', 'Value']);

        $table->addRow(['Current Usage', $usage['memory_usage_formatted']]);
        $table->addRow(['Peak Usage', $usage['memory_peak_usage_formatted']]);
        $table->addRow(['Memory Limit', $usage['memory_limit']]);
        
        // 计算使用百分比
        if ($usage['memory_limit'] !== '-1') {
            $limitBytes = $this->parseMemoryLimit($usage['memory_limit']);
            if ($limitBytes > 0) {
                $percentage = round(($usage['memory_usage'] / $limitBytes) * 100, 2);
                $table->addRow(['Usage Percentage', $percentage . '%']);
            }
        }

        $table->render();
        $output->writeln('');
    }

    /**
     * 显示内存泄漏检测结果
     */
    protected function displayLeakDetection(Output $output, MemoryManager $memoryManager): void
    {
        $output->writeln('<comment>Memory Leak Detection:</comment>');
        $output->writeln('');

        $detector = $memoryManager->getLeakDetector();
        $leak = $detector->detectLeak();
        $stats = $detector->getMemoryStats();

        if ($leak['leak_detected']) {
            $output->writeln('<error>⚠ Memory leak detected!</error>');
            $output->writeln("Growth: {$leak['memory_growth_formatted']} over {$leak['request_span']} requests");
            $output->writeln("Average per request: " . $this->formatBytes($leak['growth_per_request']));
        } else {
            $output->writeln('<info>✓ No significant memory leak detected</info>');
        }

        if (!empty($stats)) {
            $output->writeln('');
            $output->writeln('<comment>Memory Statistics:</comment>');
            
            $table = new Table($output);
            $table->setHeader(['Metric', 'Value']);
            
            $table->addRow(['Total Requests', $stats['total_requests']]);
            $table->addRow(['Tracked Requests', $stats['tracked_requests']]);
            $table->addRow(['Current Memory', $stats['current_memory_formatted']]);
            $table->addRow(['Total Growth', $stats['total_growth_formatted']]);
            $table->addRow(['Average per Request', $this->formatBytes($stats['average_per_request'])]);
            
            $table->render();
        }

        $output->writeln('');
    }

    /**
     * 显示清理建议
     */
    protected function displayCleanupSuggestions(Output $output, MemoryManager $memoryManager): void
    {
        $detector = $memoryManager->getLeakDetector();
        $suggestions = $detector->getCleanupSuggestions();

        if (!empty($suggestions)) {
            $output->writeln('<comment>Cleanup Suggestions:</comment>');
            $output->writeln('');
            
            foreach ($suggestions as $suggestion) {
                $output->writeln("• {$suggestion}");
            }
            
            $output->writeln('');
            $output->writeln('To perform automatic cleanup, the memory manager will handle this during normal operation.');
        } else {
            $output->writeln('<info>No cleanup suggestions at this time.</info>');
        }

        $output->writeln('');
    }

    /**
     * 解析内存限制
     */
    protected function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return -1;
        }

        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * 格式化字节数
     */
    protected function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
