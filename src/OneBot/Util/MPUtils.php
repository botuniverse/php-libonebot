<?php

declare(strict_types=1);

namespace OneBot\Util;

class MPUtils
{
    private static $process_type = ONEBOT_PROCESS_MASTER;

    /**
     * @var int
     */
    private static $process_id = -1;

    public static function initProcess(int $type, int $id): void
    {
        self::$process_type = $type;
        self::$process_id = $id;
    }

    public static function getProcessId(): int
    {
        return self::$process_id;
    }

    public static function getProcessType(): int
    {
        return self::$process_type;
    }

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
}
