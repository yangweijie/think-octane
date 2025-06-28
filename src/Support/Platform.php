<?php

declare(strict_types=1);

namespace yangweijie\thinkOctane\Support;

/**
 * 跨平台兼容性工具类
 */
class Platform
{
    /**
     * 检查是否为 Windows 系统
     */
    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * 检查是否为 Linux/Unix 系统
     */
    public static function isUnix(): bool
    {
        return !self::isWindows();
    }

    /**
     * 获取当前进程ID（跨平台兼容）
     */
    public static function getCurrentPid(): int
    {
        if (function_exists('posix_getpid')) {
            return posix_getpid();
        } else {
            return getmypid();
        }
    }

    /**
     * 检查进程是否存在（跨平台兼容）
     */
    public static function processExists(int $pid): bool
    {
        if (self::isWindows()) {
            $output = shell_exec("tasklist /FI \"PID eq $pid\" 2>NUL");
            return $output && strpos($output, (string) $pid) !== false;
        } else {
            return function_exists('posix_kill') ? posix_kill($pid, 0) : false;
        }
    }

    /**
     * 终止进程（跨平台兼容）
     */
    public static function killProcess(int $pid, bool $force = false): bool
    {
        if (self::isWindows()) {
            if ($force) {
                // 强制终止
                exec("taskkill /F /PID {$pid} 2>NUL", $output, $returnCode);
            } else {
                // 优雅终止
                exec("taskkill /T /PID {$pid} 2>NUL", $output, $returnCode);
            }
            return $returnCode === 0;
        } else {
            if (function_exists('posix_kill')) {
                $signal = $force ? (defined('SIGKILL') ? SIGKILL : 9) : (defined('SIGTERM') ? SIGTERM : 15);
                return posix_kill($pid, $signal);
            } else {
                $signal = $force ? '-9' : '-15';
                exec("kill {$signal} {$pid} 2>/dev/null", $output, $returnCode);
                return $returnCode === 0;
            }
        }
    }

    /**
     * 终止进程树（包括子进程）
     */
    public static function killProcessTree(int $pid, bool $force = false): bool
    {
        if (self::isWindows()) {
            // Windows 上终止进程树
            $flag = $force ? '/F /T' : '/T';
            exec("taskkill {$flag} /PID {$pid} 2>NUL", $output, $returnCode);
            return $returnCode === 0;
        } else {
            // Unix/Linux 上终止进程组
            if (function_exists('posix_kill')) {
                $signal = $force ? (defined('SIGKILL') ? SIGKILL : 9) : (defined('SIGTERM') ? SIGTERM : 15);
                // 尝试终止进程组
                return posix_kill(-$pid, $signal) || posix_kill($pid, $signal);
            } else {
                $signal = $force ? '-9' : '-15';
                exec("pkill {$signal} -P {$pid} 2>/dev/null", $output1, $returnCode1);
                exec("kill {$signal} {$pid} 2>/dev/null", $output2, $returnCode2);
                return $returnCode1 === 0 || $returnCode2 === 0;
            }
        }
    }

    /**
     * 检查扩展是否可用
     */
    public static function hasExtension(string $extension): bool
    {
        return extension_loaded($extension);
    }

    /**
     * 检查函数是否可用
     */
    public static function hasFunction(string $function): bool
    {
        return function_exists($function);
    }

    /**
     * 获取推荐的服务器类型
     */
    public static function getRecommendedServer(): string
    {
        if (self::hasExtension('swoole')) {
            return 'swoole';
        } elseif (class_exists('React\Http\HttpServer')) {
            return 'reactphp';
        } elseif (class_exists('Workerman\Worker')) {
            return 'workerman';
        } else {
            return 'swoole'; // 默认推荐
        }
    }

    /**
     * 获取服务器兼容性信息
     */
    public static function getServerCompatibility(): array
    {
        return [
            'swoole' => [
                'available' => self::hasExtension('swoole'),
                'windows_support' => true,
                'recommended' => true,
                'note' => 'Best performance, full Windows support',
            ],
            'reactphp' => [
                'available' => class_exists('React\Http\HttpServer'),
                'windows_support' => true,
                'recommended' => true,
                'note' => 'Good cross-platform compatibility',
            ],
            'workerman' => [
                'available' => class_exists('Workerman\Worker'),
                'windows_support' => false,
                'recommended' => self::isUnix(),
                'note' => 'Limited Windows support, better on Linux/Unix',
            ],
        ];
    }

    /**
     * 获取运行时路径
     */
    public static function getRuntimePath(): string
    {
        if (function_exists('runtime_path')) {
            return runtime_path();
        } else {
            return sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        }
    }

    /**
     * 获取环境变量值
     */
    public static function env(string $key, $default = null)
    {
        if (function_exists('env')) {
            return env($key, $default);
        } else {
            return $_ENV[$key] ?? getenv($key) ?: $default;
        }
    }

    /**
     * 根据端口查找并终止进程
     */
    public static function killProcessByPort(int $port, bool $force = false): array
    {
        $killedPids = [];

        if (self::isWindows()) {
            // Windows: 使用 netstat 查找端口占用
            exec("netstat -ano | findstr :{$port}", $output);

            foreach ($output as $line) {
                if (preg_match('/\s+(\d+)$/', trim($line), $matches)) {
                    $pid = (int) $matches[1];
                    if ($pid > 0 && self::killProcess($pid, $force)) {
                        $killedPids[] = $pid;
                    }
                }
            }
        } else {
            // Unix/Linux: 使用 lsof 或 netstat 查找端口占用
            exec("lsof -ti:{$port} 2>/dev/null", $output);

            foreach ($output as $pid) {
                $pid = (int) trim($pid);
                if ($pid > 0 && self::killProcess($pid, $force)) {
                    $killedPids[] = $pid;
                }
            }
        }

        return $killedPids;
    }
}
