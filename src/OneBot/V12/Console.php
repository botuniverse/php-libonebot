<?php

declare(strict_types=1);

namespace OneBot\V12;

class Console
{
    public static function warning($msg)
    {
        echo date('[H:i:s] ') . '[W] ' . $msg . PHP_EOL;
    }

    public static function success($msg)
    {
        echo date('[H:i:s] ') . '[S] ' . $msg . PHP_EOL;
    }

    public static function error($msg)
    {
        echo date('[H:i:s] ') . '[E] ' . $msg . PHP_EOL;
    }
}
