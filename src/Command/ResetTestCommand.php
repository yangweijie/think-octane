<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\Table;
use yangweijie\thinkOctane\Support\ThinkPHPResetter;
use yangweijie\thinkOctane\Support\DebugHelper;

/**
 * 重置测试命令
 */
class ResetTestCommand extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->setName('octane:reset-test')
             ->setDescription('Test ThinkPHP state reset functionality');
    }

    /**
     * 执行命令
     */
    protected function execute(Input $input, Output $output): int
    {
        $output->writeln('');
        $output->writeln('<info>ThinkPHP State Reset Test</info>');
        $output->writeln('');

        // 显示重置前状态
        $this->displayBeforeState($output);
        
        // 执行重置
        $this->performReset($output);
        
        // 显示重置后状态
        $this->displayAfterState($output);

        return 0;
    }

    /**
     * 显示重置前状态
     */
    protected function displayBeforeState(Output $output): void
    {
        $output->writeln('<comment>State Before Reset:</comment>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeader(['Property', 'Value']);

        $table->addRow(['Current Time', date('Y-m-d H:i:s')]);
        $table->addRow(['Microtime', microtime(true)]);
        $table->addRow(['Memory Usage', $this->formatBytes(memory_get_usage())]);
        $table->addRow(['Peak Memory', $this->formatBytes(memory_get_peak_usage())]);
        
        // 检查全局变量
        $table->addRow(['$_SERVER[REQUEST_TIME_FLOAT]', $_SERVER['REQUEST_TIME_FLOAT'] ?? 'undefined']);
        $table->addRow(['$GLOBALS[_think_start_time]', $GLOBALS['_think_start_time'] ?? 'undefined']);
        $table->addRow(['$GLOBALS[_think_start_mem]', isset($GLOBALS['_think_start_mem']) ? $this->formatBytes($GLOBALS['_think_start_mem']) : 'undefined']);

        $table->render();
        $output->writeln('');
    }

    /**
     * 执行重置
     */
    protected function performReset(Output $output): void
    {
        $output->writeln('<comment>Performing Reset...</comment>');
        $output->writeln('');

        // 模拟一些状态
        $GLOBALS['_think_start_time'] = microtime(true) - 100; // 100秒前
        $GLOBALS['_think_start_mem'] = memory_get_usage() - 1024 * 1024; // 1MB前
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 50; // 50秒前

        $output->writeln('Set some test state...');
        
        // 获取应用实例
        $app = app();
        
        // 执行重置
        ThinkPHPResetter::resetApp($app);
        
        $output->writeln('<info>Reset completed!</info>');
        $output->writeln('');
    }

    /**
     * 显示重置后状态
     */
    protected function displayAfterState(Output $output): void
    {
        $output->writeln('<comment>State After Reset:</comment>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeader(['Property', 'Value']);

        $table->addRow(['Current Time', date('Y-m-d H:i:s')]);
        $table->addRow(['Microtime', microtime(true)]);
        $table->addRow(['Memory Usage', $this->formatBytes(memory_get_usage())]);
        $table->addRow(['Peak Memory', $this->formatBytes(memory_get_peak_usage())]);
        
        // 检查全局变量
        $table->addRow(['$_SERVER[REQUEST_TIME_FLOAT]', $_SERVER['REQUEST_TIME_FLOAT'] ?? 'undefined']);
        $table->addRow(['$GLOBALS[_think_start_time]', $GLOBALS['_think_start_time'] ?? 'undefined']);
        $table->addRow(['$GLOBALS[_think_start_mem]', isset($GLOBALS['_think_start_mem']) ? $this->formatBytes($GLOBALS['_think_start_mem']) : 'undefined']);

        $table->render();
        $output->writeln('');

        // 显示重置统计
        $this->displayResetStats($output);
    }

    /**
     * 显示重置统计
     */
    protected function displayResetStats(Output $output): void
    {
        $output->writeln('<comment>Reset Statistics:</comment>');
        $output->writeln('');

        $stats = ThinkPHPResetter::getResetStats();
        $table = new Table($output);
        $table->setHeader(['Metric', 'Value']);

        $table->addRow(['Reset Time', date('Y-m-d H:i:s', (int) $stats['reset_time'])]);
        $table->addRow(['Current Memory', $this->formatBytes($stats['current_memory'])]);
        $table->addRow(['Peak Memory', $this->formatBytes($stats['peak_memory'])]);

        foreach ($stats['globals_reset'] as $global => $status) {
            $table->addRow([
                "Global: {$global}",
                $status ? '<info>✓ Reset</info>' : '<error>✗ Not Reset</error>'
            ]);
        }

        $table->render();
        $output->writeln('');

        // 显示调试信息
        if (DebugHelper::isDebugMode()) {
            $output->writeln('<info>Debug mode is enabled - state reset will be active during requests</info>');
        } else {
            $output->writeln('<comment>Debug mode is disabled - state reset will be skipped during requests</comment>');
        }

        $output->writeln('');
    }

    /**
     * 格式化字节数
     */
    protected function formatBytes($bytes): string
    {
        if (!is_numeric($bytes)) {
            return (string) $bytes;
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
