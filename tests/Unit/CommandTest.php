<?php

declare(strict_types=1);

use yangweijie\thinkOctane\Command\StartCommand;
use yangweijie\thinkOctane\Command\StopCommand;
use yangweijie\thinkOctane\Command\ReloadCommand;
use yangweijie\thinkOctane\Command\StatusCommand;
use yangweijie\thinkOctane\Command\InstallCommand;
use think\console\Input;
use think\console\Output;

it('can create start command', function () {
    $command = new StartCommand();

    expect($command)->toBeInstanceOf(StartCommand::class);
    expect($command->getName())->toBe('octane:start');
    expect($command->getDescription())->toBe('Start the Octane server');
});

it('can create stop command', function () {
    $command = new StopCommand();

    expect($command)->toBeInstanceOf(StopCommand::class);
    expect($command->getName())->toBe('octane:stop');
    expect($command->getDescription())->toBe('Stop the Octane server');
});

it('can create reload command', function () {
    $command = new ReloadCommand();

    expect($command)->toBeInstanceOf(ReloadCommand::class);
    expect($command->getName())->toBe('octane:reload');
    expect($command->getDescription())->toBe('Reload the Octane server');
});

it('can create status command', function () {
    $command = new StatusCommand();

    expect($command)->toBeInstanceOf(StatusCommand::class);
    expect($command->getName())->toBe('octane:status');
    expect($command->getDescription())->toBe('Show the status of the Octane server');
});

it('can handle invalid server type in start command', function () {
    $app = $this->getApp();
    $command = new StartCommand();
    $command->setApp($app);

    // 测试创建无效服务器类型应该抛出异常
    expect(function () use ($command) {
        $this->callPrivateMethod($command, 'createServer', ['invalid_server']);
    })->toThrow(\InvalidArgumentException::class);
});

it('can test install command functionality', function () {
    $command = new InstallCommand();

    expect($command)->toBeInstanceOf(InstallCommand::class);
    expect($command->getName())->toBe('octane:install');
    expect($command->getDescription())->toBe('Install the Octane configuration file');
});
