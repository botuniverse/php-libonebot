<?php


namespace OneBot\V12;


class Console
{
    public static function warning($msg) {
        echo date("[H:i:s] ") . "[W] " . $msg . PHP_EOL;
    }

    public static function success($msg) {
        echo date("[H:i:s] ") . "[S] " . $msg . PHP_EOL;
    }
}