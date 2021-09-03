<?php


namespace OneBot\V12;


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
            /*
            $handler = OneBot::getInstance()->getCoreActionHandler();
            $emit = $core_camel;
            /** @var CoreActionInterface $handler_obj *
            $handler_obj = new $handler();
            $handler_obj->echo = $action_obj->echo;
            $response = $handler_obj->$emit($action_obj->params);*/
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
}