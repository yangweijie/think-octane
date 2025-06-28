<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Server;

use think\App;

/**
 * 服务器接口
 * 
 * 定义所有服务器适配器必须实现的方法
 */
interface ServerInterface
{
    /**
     * 构造函数
     * 
     * @param App $app ThinkPHP应用实例
     * @param array $config 服务器配置
     */
    public function __construct(App $app, array $config = []);

    /**
     * 启动服务器
     * 
     * @param string $host 监听地址
     * @param int $port 监听端口
     * @return void
     */
    public function start(string $host, int $port): void;

    /**
     * 停止服务器
     * 
     * @return void
     */
    public function stop(): void;

    /**
     * 重载服务器
     * 
     * @return void
     */
    public function reload(): void;

    /**
     * 获取服务器状态
     * 
     * @return array
     */
    public function status(): array;

    /**
     * 检查服务器是否正在运行
     * 
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * 获取服务器名称
     * 
     * @return string
     */
    public function getName(): string;

    /**
     * 获取PID文件路径
     * 
     * @return string
     */
    public function getPidFile(): string;

    /**
     * 设置配置
     * 
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void;

    /**
     * 获取配置
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(?string $key = null, $default = null);
}
