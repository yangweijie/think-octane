<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\Table;
use yangweijie\thinkOctane\Support\DebugHelper;

/**
 * 调试信息命令
 */
class DebugCommand extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->setName('octane:debug')
             ->setDescription('Show debug information and configuration');
    }

    /**
     * 执行命令
     */
    protected function execute(Input $input, Output $output): int
    {
        $output->writeln('');
        $output->writeln('<info>ThinkOctane Debug Information</info>');
        $output->writeln('');

        // 显示调试配置
        $this->displayDebugConfig($output);
        
        // 显示环境信息
        $this->displayEnvironmentInfo($output);
        
        // 显示调试包信息
        $this->displayDebugPackages($output);

        return 0;
    }

    /**
     * 显示调试配置
     */
    protected function displayDebugConfig(Output $output): void
    {
        $output->writeln('<comment>Debug Configuration:</comment>');
        $output->writeln('');

        $debugInfo = DebugHelper::getDebugInfo();
        $table = new Table($output);
        $table->setHeader(['Setting', 'Value']);

        $table->addRow(['Debug Mode', $debugInfo['debug_mode'] ? '<info>Enabled</info>' : '<error>Disabled</error>']);
        $table->addRow(['Has Think-Trace', $debugInfo['has_think_trace'] ? '<info>Yes</info>' : '<error>No</error>']);
        $table->addRow(['Has Debug Packages', $debugInfo['has_debug_packages'] ? '<info>Yes</info>' : '<error>No</error>']);
        $table->addRow(['Preserve Output', $debugInfo['should_preserve_output'] ? '<info>Yes</info>' : '<error>No</error>']);

        $table->render();
        $output->writeln('');
    }

    /**
     * 显示环境信息
     */
    protected function displayEnvironmentInfo(Output $output): void
    {
        $output->writeln('<comment>Environment Variables:</comment>');
        $output->writeln('');

        $debugInfo = DebugHelper::getDebugInfo();
        $table = new Table($output);
        $table->setHeader(['Variable', 'Value']);

        foreach ($debugInfo['constants'] as $name => $value) {
            $table->addRow(['Constant: ' . $name, $this->formatValue($value)]);
        }

        foreach ($debugInfo['env_vars'] as $name => $value) {
            $table->addRow(['ENV: ' . $name, $this->formatValue($value)]);
        }

        $table->render();
        $output->writeln('');
    }

    /**
     * 显示调试包信息
     */
    protected function displayDebugPackages(Output $output): void
    {
        $output->writeln('<comment>Debug Packages:</comment>');
        $output->writeln('');

        $packages = [
            'think-trace' => [
                'class' => 'think\\trace\\TraceDebug',
                'path' => getcwd() . '/vendor/topthink/think-trace',
            ],
            'think-debug' => [
                'class' => 'think\\debug\\Console',
                'path' => getcwd() . '/vendor/topthink/think-debug',
            ],
        ];

        $table = new Table($output);
        $table->setHeader(['Package', 'Class Available', 'Path Exists']);

        foreach ($packages as $name => $info) {
            $classExists = class_exists($info['class']);
            $pathExists = file_exists($info['path']);
            
            $table->addRow([
                $name,
                $classExists ? '<info>✓ Yes</info>' : '<error>✗ No</error>',
                $pathExists ? '<info>✓ Yes</info>' : '<error>✗ No</error>',
            ]);
        }

        $table->render();
        $output->writeln('');

        // 显示调试服务
        $debugInfo = DebugHelper::getDebugInfo();
        if (!empty($debugInfo['debug_services'])) {
            $output->writeln('<comment>Protected Debug Services:</comment>');
            foreach ($debugInfo['debug_services'] as $service) {
                $output->writeln("  • {$service}");
            }
            $output->writeln('');
        }
    }

    /**
     * 格式化值显示
     */
    protected function formatValue($value): string
    {
        if ($value === true) {
            return '<info>true</info>';
        } elseif ($value === false) {
            return '<error>false</error>';
        } elseif ($value === null) {
            return '<comment>null</comment>';
        } elseif ($value === 'undefined') {
            return '<comment>undefined</comment>';
        } else {
            return (string) $value;
        }
    }
}
