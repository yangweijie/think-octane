# ThinkOctane

[![Latest Version on Packagist](https://img.shields.io/packagist/v/yangweijie/think-octane.svg?style=flat-square)](https://packagist.org/packages/yangweijie/think-octane)
[![Tests](https://img.shields.io/github/actions/workflow/status/yangweijie/think-octane/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/yangweijie/think-octane/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/yangweijie/think-octane.svg?style=flat-square)](https://packagist.org/packages/yangweijie/think-octane)

ThinkOctane 是一个高性能的 ThinkPHP 应用服务器扩展，类似于 Laravel Octane，支持 Swoole、Workerman 和 ReactPHP 等多种高性能服务器。

## 特性

- 🚀 **高性能**: 应用常驻内存，避免重复启动开销
- 🔧 **多服务器支持**: 支持 Swoole、Workerman、ReactPHP
- 🎯 **内存管理**: 智能内存清理和垃圾回收
- 🔄 **热重载**: 支持开发环境热重载
- 📊 **监控统计**: 提供详细的服务器状态和性能统计
- 🛠️ **命令行工具**: 完整的命令行管理工具

## 环境要求

- PHP >= 8.1
- ThinkPHP >= 8.0
- 以下扩展之一：
  - Swoole >= 5.0 (推荐)
  - Workerman >= 4.0
  - ReactPHP HTTP >= 1.8

## 安装

使用 Composer 安装：

```bash
composer require yangweijie/think-octane
```

### 安装服务器扩展

根据你选择的服务器类型安装相应的扩展：

**Swoole (推荐)**
```bash
# 通过 PECL 安装
pecl install swoole

# 或者通过包管理器安装
# Ubuntu/Debian
sudo apt-get install php-swoole

# CentOS/RHEL
sudo yum install php-swoole
```

**Workerman**
```bash
composer require workerman/workerman
```

**ReactPHP**
```bash
composer require react/http react/socket
```

## 配置

发布配置文件：

```bash
php think octane:install
```

这将创建 `config/octane.php` 配置文件。

### 配置选项

```php
return [
    // 默认服务器类型
    'server' => env('OCTANE_SERVER', 'swoole'),
    
    // 服务器配置
    'host' => env('OCTANE_HOST', '127.0.0.1'),
    'port' => (int) env('OCTANE_PORT', 8000),
    'workers' => env('OCTANE_WORKERS', 4),
    'max_requests' => env('OCTANE_MAX_REQUESTS', 500),
    
    // 服务器特定配置
    'swoole' => [
        'options' => [
            'worker_num' => 4,
            'task_worker_num' => 0,
            'max_request' => 500,
            // 更多 Swoole 配置...
        ],
    ],
    
    'workerman' => [
        'worker_num' => 4,
        'max_requests' => 500,
        // 更多 Workerman 配置...
    ],
    
    'reactphp' => [
        'worker_num' => 4,
        'max_requests' => 500,
        // 更多 ReactPHP 配置...
    ],
    
    // 预热和清理配置
    'warm' => [
        // 需要预热的服务
    ],
    
    'flush' => [
        // 需要在每个请求后清理的服务
        'cache',
        'session',
    ],
];
```

## 使用方法

### 检查系统兼容性

在开始使用之前，建议先检查系统兼容性：

```bash
php think octane:check
```

### 启动服务器

```bash
# 使用默认配置启动
php think octane:start

# 指定服务器类型
php think octane:start swoole
php think octane:start workerman
php think octane:start reactphp

# 自定义配置
php think octane:start --host=0.0.0.0 --port=9000 --workers=8
```

### 停止服务器

```bash
php think octane:stop
```

### 重载服务器

```bash
php think octane:reload
```

### 查看服务器状态

```bash
php think octane:status
```

## 性能优化

### 内存管理

ThinkOctane 提供智能的内存管理机制：

- 自动清理全局变量
- 智能垃圾回收
- 内存使用监控
- 防止内存泄漏

### 请求处理优化

- 应用预热机制
- 连接池复用
- 协程支持（Swoole）
- 异步任务处理

## 平台兼容性

### Windows 支持

ThinkOctane 提供了良好的 Windows 兼容性：

- **Swoole**: ✅ 完全支持，推荐在 Windows 上使用
- **ReactPHP**: ✅ 完全支持，跨平台兼容性好
- **Workerman**: ⚠️ 有限支持，建议在 Linux/Unix 上使用

### 兼容性检查

使用内置命令检查系统兼容性：

```bash
php think octane:check
```

## 开发环境

### 热重载

在开发环境中启用文件监控：

```bash
php think octane:start --watch
```

或在配置文件中设置：

```php
'watch' => [
    'enabled' => env('OCTANE_WATCH', true),
    'directories' => ['app', 'config', 'route'],
    'extensions' => ['php'],
],
```

## 生产环境部署

### 使用 Supervisor

创建 Supervisor 配置文件 `/etc/supervisor/conf.d/octane.conf`：

```ini
[program:octane]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/think octane:start --host=0.0.0.0 --port=8000
directory=/path/to/your/app
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/octane.log
```

### 使用 Systemd

创建服务文件 `/etc/systemd/system/octane.service`：

```ini
[Unit]
Description=ThinkOctane Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/your/app
ExecStart=/usr/bin/php think octane:start --host=0.0.0.0 --port=8000
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

## 测试

运行测试套件：

```bash
composer test
```

运行测试并生成覆盖率报告：

```bash
composer test-coverage
```

## 贡献

欢迎贡献代码！请查看 [CONTRIBUTING.md](CONTRIBUTING.md) 了解详细信息。

## 安全

如果你发现安全漏洞，请发送邮件到 yangweijie@example.com。

## 许可证

MIT 许可证。详情请查看 [License File](LICENSE.md)。

## 致谢

- [Laravel Octane](https://laravel.com/docs/octane) - 灵感来源
- [Swoole](https://www.swoole.com/) - 高性能网络框架
- [Workerman](https://www.workerman.net/) - 高性能 PHP Socket 服务器框架
- [ReactPHP](https://reactphp.org/) - 事件驱动的非阻塞 I/O 库
