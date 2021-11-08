<?php


namespace OneBot\V12;


use OneBot\V12\Action\ActionBase;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\ActionObject;
use ReflectionClass;

class Utils
{
    public static $cache = [];

    /**
     * @param $arr
     * @return bool
     */
    public static function isAssocArray($arr): bool {
        return array_values($arr) !== $arr;
    }

    /**
     * @param ActionObject $action_obj
     * @return int
     */
    public static function getActionType(ActionObject $action_obj): int {
        $action = $action_obj->action;
        if (isset(self::getCoreActionMethods()[$action])) {
            return ONEBOT_CORE_ACTION;
        } elseif (isset(OneBot::getInstance()->getExtendedActions()[$action])) {
            return ONEBOT_EXTENDED_ACTION;
        } else {
            return ONEBOT_UNKNOWN_ACTION;
        }
    }

    public static function separatorToCamel($name, $separator = "_"): string {
        $name = $separator . str_replace($separator, " ", strtolower($name));
        return ltrim(str_replace(" ", "", ucwords($name)), $separator);
    }

    public static function camelToSeparator($str, $separator = "_"): string {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $separator . "$2", $str));
    }

    public static function getCoreActionMethods(): array {
        if (isset(Utils::$cache["core_action_methods"])) {
            return Utils::$cache["core_action_methods"];
        } else {
            $reflection = new ReflectionClass(CoreActionInterface::class);
            $list = [];
            foreach ($reflection->getMethods() as $k => $v) {
                $method_name = substr($v->getName(), 2);
                $list[self::camelToSeparator($method_name)] = $v->getName();
            }
            return Utils::$cache["core_action_methods"] = $list;
        }
    }

    public static function msgToString($message): string {
        $result = "";
        if (is_array($message)) {
            foreach($message as $v) {
                if ($v["type"] === "text") {
                    $result .= $v["data"]["text"];
                }
            }
        }
        return $result;
    }

    /**
     * @throws OneBotFailureException
     */
    public static function getActionFuncName(ActionBase $handler, string $action) {
        if (isset(ActionBase::$core_cache[$action])) {
            return ActionBase::$core_cache[$action];
        } elseif (isset(ActionBase::$ext_cache[$action])) {
            return ActionBase::$ext_cache[$action];
        } elseif (substr($action, 0, strlen(OneBot::getInstance()->getPlatform()) + 1) === (OneBot::getInstance()->getPlatform() . ".")) {
            $func = self::separatorToCamel('ext_' . substr($action, strlen(OneBot::getInstance()->getPlatform()) + 1));
            if (method_exists($handler, $func)) {
                return ActionBase::$ext_cache[$action] = $func;
            } else {
                throw new OneBotFailureException(RetCode::UNSUPPORTED_ACTION);
            }
        } else {
            $func = self::separatorToCamel('on_' . $action);
            if (method_exists($handler, $func)) {
                return ActionBase::$core_cache[$action] = $func;
            } else {
                throw new OneBotFailureException(RetCode::UNSUPPORTED_ACTION);
            }
        }
    }
}