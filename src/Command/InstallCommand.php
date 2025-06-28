<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * Octane安装命令
 */
class InstallCommand extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->setName('octane:install')
             ->setDescription('Install the Octane configuration file');
    }

    /**
     * 执行命令
     */
    protected function execute(Input $input, Output $output): int
    {
        try {
            $this->publishConfig($output);
            $this->displayInstallationInfo($output);
            
            return 0;
            
        } catch (\Throwable $e) {
            $output->error("Failed to install Octane: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * 发布配置文件
     */
    protected function publishConfig(Output $output): void
    {
        $sourceConfig = __DIR__ . '/../../config/octane.php';
        $targetConfig = $this->app->getConfigPath() . 'octane.php';
        
        if (file_exists($targetConfig)) {
            $output->warning("Configuration file already exists: {$targetConfig}");
            return;
        }
        
        $configDir = dirname($targetConfig);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        if (copy($sourceConfig, $targetConfig)) {
            $output->info("Configuration file published: {$targetConfig}");
        } else {
            throw new \RuntimeException("Failed to publish configuration file");
        }
    }

    /**
     * 显示安装信息
     */
    protected function displayInstallationInfo(Output $output): void
    {
        $output->writeln('');
        $output->writeln('<info>ThinkOctane installed successfully!</info>');
        $output->writeln('');
        $output->writeln('<comment>Next steps:</comment>');
        $output->writeln('');
        $output->writeln('1. Install a server extension:');
        $output->writeln('   • Swoole: pecl install swoole');
        $output->writeln('   • Workerman: composer require workerman/workerman');
        $output->writeln('   • ReactPHP: composer require react/http react/socket');
        $output->writeln('');
        $output->writeln('2. Configure your server in config/octane.php');
        $output->writeln('');
        $output->writeln('3. Start the server:');
        $output->writeln('   php think octane:start');
        $output->writeln('');
    }
}
