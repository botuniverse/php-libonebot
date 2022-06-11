<?php

declare(strict_types=1);

namespace OneBot\Driver;

class ProcessManager
{
    /** @var int 进程类型 */
    private static $process_type = ONEBOT_PROCESS_MASTER;

    /** @var int 进程 ID */
    private static $process_id = -1;

    /**
     * 初始化进程
     *
     * @param int $type 进程类型
     * @param int $id   进程 ID
     */
    public static function initProcess(int $type, int $id): void
    {
        self::$process_type = $type;
        self::$process_id = $id;
    }

    /**
     * 获取进程 ID
     */
    public static function getProcessId(): int
    {
        return self::$process_id;
    }

    /**
     * 获取进程类型
     */
    public static function getProcessType(): int
    {
        return self::$process_type;
    }

    /**
     * 获取进程日志前缀
     */
    public static function getProcessLogName(): string
    {
        switch (self::$process_type) {
            case ONEBOT_PROCESS_MANAGER:
                return '[MANAGER] ';
            case ONEBOT_PROCESS_WORKER:
            case ONEBOT_PROCESS_TASKWORKER:
                return '[#' . self::$process_id . '] ';
            case ONEBOT_PROCESS_USER:
                return '[USER] ';
            default:
                return '';
        }
    }

    /**
     * 检查系统环境是否支持多进程模式
     */
    public static function isSupportedMultiProcess(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\' && Driver::getActiveDriverClass() === WorkermanDriver::class) {
            // 判断是不是 Windows 下运行的，如果是 Windows 下，则不支持
            return false;
        }
        if (Driver::getActiveDriverClass() === WorkermanDriver::class && !extension_loaded('pcntl')) {
            // 如果是 Workerman 下运行，则判断是否支持 pcntl 扩展
            return false;
        }
        return true;
    }
}
