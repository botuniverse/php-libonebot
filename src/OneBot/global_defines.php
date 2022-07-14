<?php

declare(strict_types=1);

use OneBot\Driver\Process\ProcessManager;
use OneBot\V12\OneBot;
use Psr\Log\LoggerInterface;
use ZM\Logger\ConsoleLogger;

const ONEBOT_VERSION = '12';
const ONEBOT_LIBOB_VERSION = '0.4.0';

const ONEBOT_JSON = 1;
const ONEBOT_MSGPACK = 2;

const ONEBOT_CORE_ACTION = 1;
const ONEBOT_EXTENDED_ACTION = 2;
const ONEBOT_UNKNOWN_ACTION = 0;

const ONEBOT_PROCESS_MASTER = 1;
const ONEBOT_PROCESS_MANAGER = 2;
const ONEBOT_PROCESS_WORKER = 4;
const ONEBOT_PROCESS_USER = 8;
const ONEBOT_PROCESS_TASKWORKER = 16;

/**
 * 更漂亮的dump变量
 *
 * @param  mixed       $var
 * @return array|mixed
 */
function ob_dump($var, ...$moreVars)
{
    if (class_exists('\Symfony\Component\VarDumper\VarDumper')) {
        Symfony\Component\VarDumper\VarDumper::dump($var);
        foreach ($moreVars as $v) {
            Symfony\Component\VarDumper\VarDumper::dump($v);
        }
    } elseif (PHP_VERSION >= 8.0) {
        var_dump($var, ...$moreVars);
    } else {
        var_dump($var);
        foreach ($moreVars as $v) {
            var_dump($v);
        }
    }
    if (1 < func_num_args()) {
        return func_get_args();
    }
    return $var;
}

/**
 * 获取 OneBot 日志实例
 */
function ob_logger(): LoggerInterface
{
    global $ob_logger;
    return $ob_logger;
}

function ob_logger_register(LoggerInterface $logger): void
{
    global $ob_logger;
    if ($logger instanceof ConsoleLogger) {
        $type = ProcessManager::getProcessType();
        $map = [
            ONEBOT_PROCESS_MASTER => 'MST',
            ONEBOT_PROCESS_MANAGER => 'MAN',
            ONEBOT_PROCESS_WORKER => '#' . ProcessManager::getProcessId(),
            ONEBOT_PROCESS_USER => 'USR',
            ONEBOT_PROCESS_TASKWORKER => '#' . ProcessManager::getProcessId(),
        ];
        $logger::$format = '[%date%] [%level%] [' . $map[$type] . '] %body%';
        $logger::$date_format = 'Y-m-d H:i:s';
    }
    $ob_logger = $logger;
}

/**
 * 获取 OneBot 配置实例
 *
 * @param  null|mixed $default
 * @return mixed
 */
function ob_config(string $key = null, $default = null)
{
    $config = OneBot::getInstance()->getConfig();
    if (!is_null($key)) {
        $config = $config->get($key, $default);
    }
    return $config;
}

/**
 * 生成 UUID
 *
 * @param bool $uppercase 是否大写
 */
function ob_uuidgen(bool $uppercase = false): string
{
    try {
        $data = random_bytes(16);
    } catch (Exception $e) {
        throw new RuntimeException('Failed to generate UUID: ' . $e->getMessage(), $e->getCode(), $e);
    }
    $data[6] = chr(ord($data[6]) & 0x0F | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3F | 0x80);
    return $uppercase ? strtoupper(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4))) :
        vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * 判断当前驱动是否为指定驱动
 *
 * @param string $driver 驱动名称
 */
function ob_driver_is(string $driver): bool
{
    return get_class(OneBot::getInstance()->getDriver()) === $driver;
}
