<?php

declare(strict_types=1);

use OneBot\V12\Exception\OneBotException;
use OneBot\V12\OneBot;
use Psr\Log\LoggerInterface;

const ONEBOT_VERSION = '12';
const ONEBOT_LIBOB_VERSION = '0.3.0';

const ONEBOT_JSON = 1;
const ONEBOT_MSGPACK = 2;

const ONEBOT_CORE_ACTION = 1;
const ONEBOT_EXTENDED_ACTION = 2;
const ONEBOT_UNKNOWN_ACTION = 0;

define('ONEBOT_PROCESS_MASTER', 1);
define('ONEBOT_PROCESS_MANAGER', 2);
define('ONEBOT_PROCESS_WORKER', 4);
define('ONEBOT_PROCESS_USER', 8);
define('ONEBOT_PROCESS_TASKWORKER', 16);

/**
 * 更漂亮的dump变量
 *
 * @param $var
 * @param ...$moreVars
 * @return array|mixed
 */
function ob_dump($var, ...$moreVars)
{
    if (class_exists('\Symfony\Component\VarDumper\VarDumper')) {
        Symfony\Component\VarDumper\VarDumper::dump($var);
        foreach ($moreVars as $v) {
            Symfony\Component\VarDumper\VarDumper::dump($v);
        }
    } else {
        var_dump($var, ...$moreVars);
    }
    if (1 < func_num_args()) {
        return func_get_args();
    }
    return $var;
}

/**
 * 更漂亮的logger输出
 */
function ob_logger(): LoggerInterface
{
    return OneBot::getInstance()->getLogger();
}

/**
 * 返回ob配置项
 *
 * @param  null  $default
 * @return mixed
 */
function ob_config(string $key = null, $default = null)
{
    $config = OneBot::getInstance()->getDriver()->getConfig();
    if (!is_null($key)) {
        /** @var mixed $config */
        $config = $config->get($key, $default);
    }

    return $config;
}

/**
 * @throws OneBotException
 */
function ob_uuidgen(bool $uppercase = false): string
{
    try {
        $data = random_bytes(16);
    } catch (Exception $e) {
        throw new OneBotException('Failed to generate UUID: ' . $e->getMessage(), $e->getCode(), $e);
    }
    $data[6] = chr(ord($data[6]) & 0x0F | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3F | 0x80);
    return $uppercase ? strtoupper(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4))) :
        vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
