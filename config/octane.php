<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    |
    | 这里配置Octane使用的服务器类型。支持的服务器包括：
    | "swoole", "workerman", "reactphp"
    |
    */
    'server' => function_exists('env') ? env('OCTANE_SERVER', 'swoole') : 'swoole',

    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    |
    | 服务器配置选项
    |
    */
    'host' => function_exists('env') ? env('OCTANE_HOST', '127.0.0.1') : '127.0.0.1',
    'port' => function_exists('env') ? (int) env('OCTANE_PORT', 8000) : 8000,

    /*
    |--------------------------------------------------------------------------
    | Worker Configuration
    |--------------------------------------------------------------------------
    |
    | 工作进程配置
    |
    */
    'workers' => function_exists('env') ? env('OCTANE_WORKERS', 4) : 4,
    'task_workers' => function_exists('env') ? env('OCTANE_TASK_WORKERS', 0) : 0,
    'max_requests' => function_exists('env') ? env('OCTANE_MAX_REQUESTS', 500) : 500,

    /*
    |--------------------------------------------------------------------------
    | Swoole Configuration
    |--------------------------------------------------------------------------
    |
    | Swoole服务器特定配置
    |
    */
    'swoole' => [
        'options' => [
            'log_file' => function_exists('runtime_path') ? runtime_path() . 'swoole.log' : sys_get_temp_dir() . '/swoole.log',
            'log_level' => defined('SWOOLE_LOG_INFO') ? SWOOLE_LOG_INFO : 0,
            'worker_num' => function_exists('env') ? env('OCTANE_WORKERS', 4) : 4,
            'task_worker_num' => function_exists('env') ? env('OCTANE_TASK_WORKERS', 0) : 0,
            'max_request' => function_exists('env') ? env('OCTANE_MAX_REQUESTS', 500) : 500,
            'reload_async' => true,
            'max_wait_time' => 60,
            'enable_reuse_port' => true,
            'enable_coroutine' => true,
            'send_yield' => true,
            'hook_flags' => defined('SWOOLE_HOOK_ALL') ? SWOOLE_HOOK_ALL : 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workerman Configuration
    |--------------------------------------------------------------------------
    |
    | Workerman服务器特定配置
    |
    */
    'workerman' => [
        'worker_num' => function_exists('env') ? env('OCTANE_WORKERS', 4) : 4,
        'max_requests' => function_exists('env') ? env('OCTANE_MAX_REQUESTS', 500) : 500,
        'memory_limit' => '128M',
        'log_file' => function_exists('runtime_path') ? runtime_path() . 'workerman.log' : sys_get_temp_dir() . '/workerman.log',
    ],

    /*
    |--------------------------------------------------------------------------
    | ReactPHP Configuration
    |--------------------------------------------------------------------------
    |
    | ReactPHP服务器特定配置
    |
    */
    'reactphp' => [
        'worker_num' => function_exists('env') ? env('OCTANE_WORKERS', 4) : 4,
        'max_requests' => function_exists('env') ? env('OCTANE_MAX_REQUESTS', 500) : 500,
        'memory_limit' => '128M',
        'log_file' => function_exists('runtime_path') ? runtime_path() . 'reactphp.log' : sys_get_temp_dir() . '/reactphp.log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm / Flush
    |--------------------------------------------------------------------------
    |
    | 这里配置需要在每个请求之间清理的服务和需要预热的服务
    |
    */
    'warm' => [
        // 需要预热的服务
    ],

    'flush' => [
        // 需要在每个请求后清理的服务
        'cache',
        'session',
    ],

    /*
    |--------------------------------------------------------------------------
    | Garbage Collection
    |--------------------------------------------------------------------------
    |
    | 垃圾回收配置
    |
    */
    'garbage_collection' => [
        'enabled' => true,
        'probability' => 50, // 1-100
        'cycles' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Watch Configuration
    |--------------------------------------------------------------------------
    |
    | 文件监控配置，用于开发环境热重载
    |
    */
    'watch' => [
        'enabled' => function_exists('env') ? env('OCTANE_WATCH', false) : false,
        'directories' => [
            'app',
            'config',
            'route',
        ],
        'extensions' => ['php'],
        'ignore' => [
            'runtime',
            'vendor',
            '.git',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Octane中间件配置
    |
    */
    'middleware' => [
        // Octane特定中间件
    ],

    /*
    |--------------------------------------------------------------------------
    | Listeners
    |--------------------------------------------------------------------------
    |
    | 事件监听器配置
    |
    */
    'listeners' => [
        // 请求开始
        'RequestReceived' => [
            //
        ],
        // 请求结束
        'RequestTerminated' => [
            //
        ],
        // 任务接收
        'TaskReceived' => [
            //
        ],
        // 任务完成
        'TaskTerminated' => [
            //
        ],
        // Worker启动
        'WorkerStarting' => [
            //
        ],
        // Worker停止
        'WorkerStopping' => [
            //
        ],
    ],
];
