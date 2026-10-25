<?php

declare(strict_types=1);

namespace OneBot\Driver;

/**
 * Worker 崩溃守卫（Swoole / Workerman 共用）
 *
 * 两个驱动都会在 worker 异常退出后立刻补一个新 worker，而且没有任何退避。
 * 一旦 worker 处于「启动即死」状态（典型原因：终端被关导致 stdio 坏掉、
 * 扩展段错误、配置错误），就会退化成一秒几十次的 fork 风暴，把 CPU 打满，
 * 而现场往往一条日志都没有。本类做两件事：
 *
 * 1. 把崩溃原因（exit_code + signal）落到 zm_data/logs/worker_error.log；
 * 2. 统计窗口内的崩溃次数，超过阈值就判定为「崩溃风暴」，由调用方停机。
 *
 * 日志刻意不走 ob_logger()：worker 崩溃的原因很可能就是控制台输出坏了
 * （IDE 终端关闭后 fd 被 revoked），再调 logger 会把宿主进程一起带走。
 */
class WorkerCrashGuard
{
    /** @var float[] 最近崩溃时间戳（只在 master/manager 进程内有效，进程重启后自动清零） */
    private static array $crash_times = [];

    /** @var array<int, string> 常见信号名 */
    private const SIGNAL_NAMES = [
        1 => 'SIGHUP', 2 => 'SIGINT', 3 => 'SIGQUIT', 4 => 'SIGILL', 6 => 'SIGABRT', 8 => 'SIGFPE',
        9 => 'SIGKILL', 10 => 'SIGBUS', 11 => 'SIGSEGV', 13 => 'SIGPIPE', 14 => 'SIGALRM',
        15 => 'SIGTERM', 24 => 'SIGXCPU', 25 => 'SIGXFSZ',
    ];

    /** @var string|null 测试用：覆盖日志目录 */
    private static ?string $log_dir = null;

    /** 日志文件超过该大小时滚动一次，避免反复崩溃把磁盘写满 */
    public const MAX_LOG_BYTES = 10 * 1024 * 1024;

    /**
     * 记录一次 worker 崩溃
     *
     * @param string                                                  $driver    驱动名（swoole / workerman），只用于日志
     * @param int                                                     $worker_pid 崩溃的 worker pid
     * @param int                                                     $exit_code  退出码（signal 为 0 时有效，255 多为未捕获异常）
     * @param int                                                     $signal     杀死进程的信号，0 表示正常退出码路径
     * @param array{window?: int, limit?: int} $options  window: 统计窗口秒数；limit: 触发熔断的次数（0 = 关闭）
     * @param string|null                                             $context 附加到日志里的上下文（如 worker_id）
     *
     * @return bool 是否触发熔断（调用方据此停机）
     */
    public static function report(string $driver, int $worker_pid, int $exit_code, int $signal, array $options = [], ?string $context = null): bool
    {
        $window = max(1, (int) ($options['window'] ?? 10));
        $limit = (int) ($options['limit'] ?? 10);

        $now = microtime(true);
        self::$crash_times[] = $now;
        self::$crash_times = array_values(array_filter(
            self::$crash_times,
            static fn (float $ts): bool => $now - $ts <= $window
        ));
        $recent = count(self::$crash_times);

        self::write(sprintf(
            '%s worker 异常退出: pid=%d, exit_code=%d, signal=%d(%s), 近 %d 秒内第 %d 次%s%s',
            $driver,
            $worker_pid,
            $exit_code,
            $signal,
            self::signalName($signal),
            $window,
            $recent,
            self::signalHint($exit_code, $signal),
            $context === null ? '' : ', ' . $context
        ));

        if ($limit > 0 && $recent >= $limit) {
            self::write(sprintf(
                '检测到 Worker 崩溃风暴: %s 在 %d 秒内崩溃 %d 次（阈值 %d），将主动中止服务器，'
                . '避免无限重启把 CPU 打满。请根据上面的 exit_code/signal 排查根因。',
                $driver,
                $window,
                $recent,
                $limit
            ));
            return true;
        }

        return false;
    }

    /**
     * 覆盖日志目录（测试用）
     */
    public static function setLogDir(?string $dir): void
    {
        self::$log_dir = $dir;
    }

    /**
     * 清空崩溃统计（测试用；正常流程不需要调用，进程重启即自然清零）
     */
    public static function reset(): void
    {
        self::$crash_times = [];
    }

    /**
     * 当前窗口内的崩溃次数（测试用）
     */
    public static function recentCount(): int
    {
        return count(self::$crash_times);
    }

    public static function signalName(int $signal): string
    {
        return self::SIGNAL_NAMES[$signal] ?? 'SIG' . $signal;
    }

    public static function signalHint(int $exit_code, int $signal): string
    {
        return match ($signal) {
            11, 10, 4 => '（段错误/总线错误：多半是 PHP 扩展或框架层的非法内存访问，日志/IO 递归写入坏掉的控制台也可能触发）',
            6 => '（abort：PHP 内部致命错误）',
            9 => '（被 kill -9 或系统因内存不足杀掉）',
            13 => '（SIGPIPE：往已关闭的管道/tty 写数据，常见于启动它的终端被关掉）',
            15, 2 => '（收到正常停止信号，若频繁出现请检查是否有外部进程在反复 kill/reload）',
            0 => $exit_code === 255 ? '（未捕获异常或 PHP Fatal Error，需要看 worker 的标准错误输出）' : '',
            default => '',
        };
    }

    /**
     * 追加一行崩溃日志到 zm_data/logs/worker_error.log
     *
     * @param string $message 日志正文（会自动加时间戳与前缀）
     */
    public static function write(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] [WORKER_ERROR] ' . $message . PHP_EOL;
        try {
            $dir = self::$log_dir ?? ((defined('WORKING_DIR') ? WORKING_DIR : (string) getcwd()) . '/zm_data/logs');
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $file = $dir . '/worker_error.log';
            if (is_file($file) && filesize($file) > self::MAX_LOG_BYTES) {
                @rename($file, $file . '.1');
            }
            if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
        } catch (\Throwable $e) {
            // 落盘失败也不能让宿主进程崩掉，继续走 error_log 兜底
        }
        @error_log(rtrim($line));
    }
}
