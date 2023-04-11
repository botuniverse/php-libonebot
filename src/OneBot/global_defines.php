<?php

declare(strict_types=1);

use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\EventProvider;
use OneBot\Driver\Interfaces\HandledDispatcherInterface;
use OneBot\Driver\Interfaces\SortedProviderInterface;
use OneBot\Driver\Process\ProcessManager;
use OneBot\V12\Object\MessageSegment;
use OneBot\V12\OneBot;
use Psr\Log\LoggerInterface;
use ZM\Logger\ConsoleLogger;

const ONEBOT_VERSION = '12';
const ONEBOT_LIBOB_VERSION = '0.6.2';

const ONEBOT_JSON = 1;
const ONEBOT_MSGPACK = 2;

const ONEBOT_TYPE_ANY = 0;
const ONEBOT_TYPE_STRING = 1;
const ONEBOT_TYPE_INT = 2;
const ONEBOT_TYPE_ARRAY = 4;
const ONEBOT_TYPE_FLOAT = 8;
const ONEBOT_TYPE_OBJECT = 16;

const ONEBOT_CORE_ACTION = 1;
const ONEBOT_EXTENDED_ACTION = 2;
const ONEBOT_UNKNOWN_ACTION = 0;

const ONEBOT_PROCESS_MASTER = 1;
const ONEBOT_PROCESS_MANAGER = 2;
const ONEBOT_PROCESS_WORKER = 4;
const ONEBOT_PROCESS_USER = 8;
const ONEBOT_PROCESS_TASKWORKER = 16;

class_alias(MessageSegment::class, 'MessageSegment');

if (DIRECTORY_SEPARATOR === '\\') {
    define('ONEBOT_TMP_DIR', 'C:\\Windows\\Temp');
} elseif (!empty(getenv('TMPDIR'))) {
    define('ONEBOT_TMP_DIR', getenv('TMPDIR'));
} elseif (is_writable('/tmp')) {
    define('ONEBOT_TMP_DIR', '/tmp');
} else {
    define('ONEBOT_TMP_DIR', getcwd() . '/.zm-tmp');
}

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

/**
 * 检查是否已经初始化了 Logger 对象，如果没有的话，返回 False
 */
function ob_logger_registered(): bool
{
    global $ob_logger;
    return isset($ob_logger);
}

/**
 * 注册一个 Logger 对象到 OneBot 中，如果已经注册了将会覆盖
 */
function ob_logger_register(LoggerInterface $logger): void
{
    global $ob_logger;
    if ($logger instanceof ConsoleLogger) {
        $type = ProcessManager::getProcessType();
        $type_map = [
            ONEBOT_PROCESS_MASTER => 'MST',
            ONEBOT_PROCESS_MANAGER => 'MAN',
            ONEBOT_PROCESS_WORKER => '#' . ProcessManager::getProcessId(),
            ONEBOT_PROCESS_USER => 'USR',
            (ONEBOT_PROCESS_WORKER | ONEBOT_PROCESS_TASKWORKER) => '%' . ProcessManager::getProcessId(),
            (ONEBOT_PROCESS_WORKER | ONEBOT_PROCESS_MASTER) => 'MST#' . ProcessManager::getProcessId(),
        ];
        $ss_type = $type_map[$type] ?? ('TYPE*' . $type);
        $logger::$format = '[%date%] [%level%] [' . $ss_type . '] %body%';
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

function ob_event_dispatcher(): HandledDispatcherInterface
{
    global $ob_event_dispatcher;
    if ($ob_event_dispatcher === null) {
        $ob_event_dispatcher = new EventDispatcher();
    }
    return $ob_event_dispatcher;
}

function ob_event_provider(): SortedProviderInterface
{
    global $ob_event_provider;
    if ($ob_event_provider === null) {
        $ob_event_provider = EventProvider::getInstance();
    }
    return $ob_event_provider;
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

/**
 * 构建消息段的助手函数
 *
 * @param string $type 类型
 * @param array  $data 字段
 */
function ob_segment(string $type, array $data = []): MessageSegment
{
    return new MessageSegment($type, $data);
}
