<?php

declare(strict_types=1);

namespace OneBot\V12;

use OneBot\V12\Action\ActionBase;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\ActionObject;
use PASVL\Validation\ValidatorBuilder;
use ReflectionClass;

class Utils
{
    public static $cache = [];

    public static function isAssocArray(array $arr): bool
    {
        return array_values($arr) !== $arr;
    }

    public static function getActionType(ActionObject $action_obj): int
    {
        $action = $action_obj->action;
        if (isset(self::getCoreActionMethods()[$action])) {
            return ONEBOT_CORE_ACTION;
        }
        if (isset(OneBot::getInstance()->getExtendedActions()[$action])) {
            return ONEBOT_EXTENDED_ACTION;
        }
        return ONEBOT_UNKNOWN_ACTION;
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

    public static function getCoreActionMethods(): array
    {
        if (isset(Utils::$cache['core_action_methods'])) {
            return Utils::$cache['core_action_methods'];
        }
        // TODO: Create CoreActionInterface
        // @phpstan-ignore-next-line
        $reflection = new ReflectionClass(CoreActionInterface::class);
        $list = [];
        foreach ($reflection->getMethods() as $k => $v) {
            $method_name = substr($v->getName(), 2);
            $list[self::camelToSeparator($method_name)] = $v->getName();
        }
        return Utils::$cache['core_action_methods'] = $list;
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
