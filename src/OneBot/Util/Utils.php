<?php

declare(strict_types=1);

namespace OneBot\Util;

use OneBot\V12\Action\ActionBase;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\OneBot;
use OneBot\V12\RetCode;

class Utils
{
    public static $cache = [];

    public static function isAssocArray(array $arr): bool
    {
        return array_values($arr) !== $arr;
    }

    public static function separatorToCamel(string $string, string $separator = '_'): string
    {
        $string = $separator . str_replace($separator, ' ', strtolower($string));
        return ltrim(str_replace(' ', '', ucwords($string)), $separator);
    }

    public static function camelToSeparator(string $string, string $separator = '_'): string
    {
        return strtolower(ltrim(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', $separator . '$0', $string), '_'));
    }

    public static function msgToString($message): string
    {
        $result = '';
        if (is_array($message)) {
            foreach ($message as $v) {
                if ($v['type'] === 'text') {
                    $result .= $v['data']['text'];
                }
            }
        } else {
            $result = $message;
        }
        return $result;
    }

    public static function getActionFuncName(ActionBase $handler, string $action)
    {
        if (isset(ActionBase::$core_cache[$action])) {
            return ActionBase::$core_cache[$action];
        }

        if (isset(ActionBase::$ext_cache[$action])) {
            return ActionBase::$ext_cache[$action];
        }
        if (substr(
            $action,
            0,
            strlen(OneBot::getInstance()->getPlatform()) + 1
        ) === (OneBot::getInstance()->getPlatform() . '.')) {
            $func = Utils::separatorToCamel('ext_' . substr($action, strlen(OneBot::getInstance()->getPlatform()) + 1));
            if (method_exists($handler, $func)) {
                return ActionBase::$ext_cache[$action] = $func;
            }
        } else {
            $func = Utils::separatorToCamel('on_' . $action);
            if (method_exists($handler, $func)) {
                return ActionBase::$core_cache[$action] = $func;
            }
        }
        throw new OneBotFailureException(RetCode::UNSUPPORTED_ACTION);
    }
}
