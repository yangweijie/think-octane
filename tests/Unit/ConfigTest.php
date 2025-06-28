<?php

declare(strict_types=1);

it('has valid default configuration', function () {
    $config = require __DIR__ . '/../../config/octane.php';
    
    expect($config)->toBeArray();
    expect($config)->toHaveKey('server');
    expect($config)->toHaveKey('host');
    expect($config)->toHaveKey('port');
    expect($config)->toHaveKey('workers');
    expect($config)->toHaveKey('max_requests');
});

it('has valid server configurations', function () {
    $config = require __DIR__ . '/../../config/octane.php';
    
    // 验证Swoole配置
    expect($config)->toHaveKey('swoole');
    expect($config['swoole'])->toHaveKey('options');
    expect($config['swoole']['options'])->toHaveKey('worker_num');
    expect($config['swoole']['options'])->toHaveKey('max_request');
    
    // 验证Workerman配置
    expect($config)->toHaveKey('workerman');
    expect($config['workerman'])->toHaveKey('worker_num');
    expect($config['workerman'])->toHaveKey('max_requests');
    
    // 验证ReactPHP配置
    expect($config)->toHaveKey('reactphp');
    expect($config['reactphp'])->toHaveKey('worker_num');
    expect($config['reactphp'])->toHaveKey('max_requests');
});

it('has valid warm and flush configurations', function () {
    $config = require __DIR__ . '/../../config/octane.php';
    
    expect($config)->toHaveKey('warm');
    expect($config)->toHaveKey('flush');
    expect($config['warm'])->toBeArray();
    expect($config['flush'])->toBeArray();
});

it('has valid garbage collection configuration', function () {
    $config = require __DIR__ . '/../../config/octane.php';
    
    expect($config)->toHaveKey('garbage_collection');
    expect($config['garbage_collection'])->toHaveKey('enabled');
    expect($config['garbage_collection'])->toHaveKey('probability');
    expect($config['garbage_collection'])->toHaveKey('cycles');
    
    expect($config['garbage_collection']['enabled'])->toBeBool();
    expect($config['garbage_collection']['probability'])->toBeInt();
    expect($config['garbage_collection']['cycles'])->toBeInt();
});

it('has valid watch configuration', function () {
    $config = require __DIR__ . '/../../config/octane.php';
    
    expect($config)->toHaveKey('watch');
    expect($config['watch'])->toHaveKey('enabled');
    expect($config['watch'])->toHaveKey('directories');
    expect($config['watch'])->toHaveKey('extensions');
    expect($config['watch'])->toHaveKey('ignore');
    
    expect($config['watch']['directories'])->toBeArray();
    expect($config['watch']['extensions'])->toBeArray();
    expect($config['watch']['ignore'])->toBeArray();
});

it('has valid listeners configuration', function () {
    $config = require __DIR__ . '/../../config/octane.php';
    
    expect($config)->toHaveKey('listeners');
    expect($config['listeners'])->toBeArray();
    
    $expectedEvents = [
        'RequestReceived',
        'RequestTerminated',
        'TaskReceived',
        'TaskTerminated',
        'WorkerStarting',
        'WorkerStopping',
    ];
    
    foreach ($expectedEvents as $event) {
        expect($config['listeners'])->toHaveKey($event);
        expect($config['listeners'][$event])->toBeArray();
    }
});

it('can merge with application configuration', function () {
    $app = $this->getApp();
    
    // 模拟应用配置
    $appConfig = [
        'server' => 'workerman',
        'host' => '0.0.0.0',
        'port' => 9000,
        'custom_option' => 'test_value',
    ];
    
    $this->mockConfig($appConfig);
    
    $config = $app->config->get('octane');
    
    expect($config['server'])->toBe('workerman');
    expect($config['host'])->toBe('0.0.0.0');
    expect($config['port'])->toBe(9000);
    expect($config['custom_option'])->toBe('test_value');
});

it('can validate configuration values', function () {
    $config = require __DIR__ . '/../../config/octane.php';
    
    // 验证端口范围
    expect($config['port'])->toBeInt();
    expect($config['port'])->toBeGreaterThan(0);
    expect($config['port'])->toBeLessThanOrEqual(65535);
    
    // 验证工作进程数
    expect($config['workers'])->toBeInt();
    expect($config['workers'])->toBeGreaterThan(0);
    
    // 验证最大请求数
    expect($config['max_requests'])->toBeInt();
    expect($config['max_requests'])->toBeGreaterThan(0);
    
    // 验证垃圾回收概率
    expect($config['garbage_collection']['probability'])->toBeInt();
    expect($config['garbage_collection']['probability'])->toBeGreaterThanOrEqual(0);
    expect($config['garbage_collection']['probability'])->toBeLessThanOrEqual(100);
});
