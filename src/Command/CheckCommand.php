<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\Table;
use yangweijie\thinkOctane\Support\Platform;

/**
 * Octane兼容性检查命令
 */
class CheckCommand extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->setName('octane:check')
             ->setDescription('Check system compatibility for Octane servers');
    }

    /**
     * 执行命令
     */
    protected function execute(Input $input, Output $output): int
    {
        $output->writeln('');
        $output->writeln('<info>ThinkOctane System Compatibility Check</info>');
        $output->writeln('');

        // 显示系统信息
        $this->displaySystemInfo($output);

        // 显示服务器兼容性
        $this->displayServerCompatibility($output);

        // 显示推荐
        $this->displayRecommendations($output);

        return 0;
    }

    /**
     * 显示系统信息
     */
    protected function displaySystemInfo(Output $output): void
    {
        $output->writeln('<comment>System Information:</comment>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeader(['Property', 'Value']);

        $table->addRow(['Operating System', PHP_OS]);
        $table->addRow(['OS Family', PHP_OS_FAMILY]);
        $table->addRow(['PHP Version', PHP_VERSION]);
        $table->addRow(['SAPI', PHP_SAPI]);
        $table->addRow(['Architecture', php_uname('m')]);

        $table->render();
        $output->writeln('');
    }

    /**
     * 显示服务器兼容性
     */
    protected function displayServerCompatibility(Output $output): void
    {
        $output->writeln('<comment>Server Compatibility:</comment>');
        $output->writeln('');

        $compatibility = Platform::getServerCompatibility();
        $table = new Table($output);
        $table->setHeader(['Server', 'Available', 'Windows Support', 'Recommended', 'Note']);

        foreach ($compatibility as $server => $info) {
            $table->addRow([
                ucfirst($server),
                $info['available'] ? '<info>✓ Yes</info>' : '<error>✗ No</error>',
                $info['windows_support'] ? '<info>✓ Yes</info>' : '<error>✗ Limited</error>',
                $info['recommended'] ? '<info>✓ Yes</info>' : '<comment>○ Optional</comment>',
                $info['note'],
            ]);
        }

        $table->render();
        $output->writeln('');
    }

    /**
     * 显示推荐
     */
    protected function displayRecommendations(Output $output): void
    {
        $output->writeln('<comment>Recommendations:</comment>');
        $output->writeln('');

        $recommended = Platform::getRecommendedServer();
        $compatibility = Platform::getServerCompatibility();

        if ($compatibility[$recommended]['available']) {
            $output->writeln("<info>✓ Recommended server: {$recommended}</info>");
            $output->writeln("  You can start the server with: <comment>php think octane:start {$recommended}</comment>");
        } else {
            $output->writeln("<error>✗ Recommended server ({$recommended}) is not available</error>");
            
            // 找到可用的服务器
            $available = array_filter($compatibility, function ($info) {
                return $info['available'];
            });

            if (!empty($available)) {
                $firstAvailable = array_key_first($available);
                $output->writeln("  Alternative: <comment>{$firstAvailable}</comment>");
                $output->writeln("  Install command: <comment>php think octane:start {$firstAvailable}</comment>");
            } else {
                $output->writeln('');
                $output->writeln('<error>No servers are currently available. Please install one of the following:</error>');
                $output->writeln('');
                $output->writeln('  <comment>Swoole:</comment>');
                $output->writeln('    pecl install swoole');
                $output->writeln('');
                $output->writeln('  <comment>Workerman:</comment>');
                $output->writeln('    composer require workerman/workerman');
                $output->writeln('');
                $output->writeln('  <comment>ReactPHP:</comment>');
                $output->writeln('    composer require react/http react/socket');
            }
        }

        // Windows 特殊提示
        if (Platform::isWindows()) {
            $output->writeln('');
            $output->writeln('<comment>Windows-specific notes:</comment>');
            $output->writeln('  • Swoole: Full Windows support with good performance');
            $output->writeln('  • ReactPHP: Good cross-platform compatibility');
            $output->writeln('  • Workerman: Limited Windows support, may have issues');
        }

        $output->writeln('');
    }
}
