<?php

declare(strict_types=1);

namespace OneBot\V12;

use OneBot\V12\Action\ActionBase;
use OneBot\V12\Exception\OneBotFailureException;
use PASVL\Validation\ValidatorBuilder;

class Utils
{
    public static $cache = [];

    public static function isAssocArray(array $arr): bool
    {
        return array_values($arr) !== $arr;
    }

    public static function separatorToCamel($name, $separator = '_'): string
    {
        $name = $separator . str_replace($separator, ' ', strtolower($name));
        return ltrim(str_replace(' ', '', ucwords($name)), $separator);
    }

    public static function camelToSeparator($str, $separator = '_'): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1' . $separator . '$2', $str));
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

    /**
     * @throws OneBotFailureException
     */
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
            $func = self::separatorToCamel('ext_' . substr($action, strlen(OneBot::getInstance()->getPlatform()) + 1));
            if (method_exists($handler, $func)) {
                return ActionBase::$ext_cache[$action] = $func;
            }
        } else {
            $func = self::separatorToCamel('on_' . $action);
            if (method_exists($handler, $func)) {
                return ActionBase::$core_cache[$action] = $func;
            }
        }
        throw new OneBotFailureException(RetCode::UNSUPPORTED_ACTION);
    }

    /**
     * 验证 $input 是否符合指定 $pattern.
     *
     * @see https://github.com/lezhnev74/pasvl
     */
    public static function validateArray(array $pattern, array $input)
    {
        $builder = ValidatorBuilder::forArray($pattern);
        $builder->build()->validate($input);
    }
}
