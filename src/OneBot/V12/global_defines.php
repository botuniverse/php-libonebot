<?php

declare(strict_types=1);

use OneBot\V12\Exception\OneBotException;

define('ONEBOT_VERSION', '12');
define('ONEBOT_LIBOB_VERSION', '0.1.0');

define('ONEBOT_JSON', 1);
define('ONEBOT_MSGPACK', 2);

define('ONEBOT_CORE_ACTION', 1);
define('ONEBOT_EXTENDED_ACTION', 2);
define('ONEBOT_UNKNOWN_ACTION', 0);

function ob_dump($var, ...$moreVars)
{
    \Symfony\Component\VarDumper\VarDumper::dump($var);
    foreach ($moreVars as $v) {
        \Symfony\Component\VarDumper\VarDumper::dump($v);
    }
    if (1 < func_num_args()) {
        return func_get_args();
    }
    return $var;
}

function logger(): Psr\Log\LoggerInterface
{
    return \OneBot\V12\OneBot::getInstance()->getLogger();
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
