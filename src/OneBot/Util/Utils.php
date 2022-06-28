<?php

declare(strict_types=1);

namespace OneBot\Util;

use OneBot\V12\Action\ActionBase;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\Action;
use OneBot\V12\OneBot;
use OneBot\V12\RetCode;

class Utils
{
    /**
     * 判断是否为关联数组
     *
     * @param array $arr 待判断数组
     */
    public static function isAssocArray(array $arr): bool
    {
        return array_values($arr) !== $arr;
    }

    /**
     * 将蛇形字符串转换为驼峰命名
     *
     * @param string $string    需要进行转换的字符串
     * @param string $separator 分隔符
     */
    public static function separatorToCamel(string $string, string $separator = '_'): string
    {
        $string = $separator . str_replace($separator, ' ', strtolower($string));
        return ltrim(str_replace(' ', '', ucwords($string)), $separator);
    }

    /**
     * 将驼峰字符串转换为蛇形命名
     *
     * @param string $string    需要进行转换的字符串
     * @param string $separator 分隔符
     */
    public static function camelToSeparator(string $string, string $separator = '_'): string
    {
        return strtolower(ltrim(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', $separator . '$0', $string), '_'));
    }

    /**
     * 将消息数组转换为字符串
     * 传入字符串时原样返回
     *
     * @param array|string $message 消息
     */
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
     * 获取动作方法名
     *
     * @throws OneBotFailureException
     */
    public static function getActionFuncName(ActionBase $handler, string $action): string
    {
        if (isset(ActionBase::$core_cache[$action])) {
            return ActionBase::$core_cache[$action];
        }

        if (isset(ActionBase::$ext_cache[$action])) {
            return ActionBase::$ext_cache[$action];
        }
        if (strpos($action, (OneBot::getInstance()->getPlatform() . '.')) === 0) {
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
     * 用于验证动作对象中的参数验证
     *
     * 如果验证失败，直接抛出 BAD_PARAM 异常。
     *
     * $array 为验证方式，目前支持两种验证：
     * 1. 如果 k => true，则验证 param 是否存在 k。
     * 2. 如果 k => {list}，则在 1 的基础上验证参数 k 是否是给定 list 中的一种。
     *
     * @throws OneBotFailureException
     */
    public static function validateParamsByAction(Action $action_obj, array $array)
    {
        $valid = true;
        foreach ($array as $k => $v) {
            if ($v === true) {
                if (!isset($action_obj->params[$k])) {
                    $valid = false;
                    break;
                }
            } elseif (!self::isAssocArray($v)) {
                if (!isset($action_obj->params[$k])) {
                    $valid = false;
                    break;
                }
                if (!in_array($action_obj->params[$k], $v)) {
                    $valid = false;
                    break;
                }
            }
        }
        if (!$valid) {
            throw new OneBotFailureException(RetCode::BAD_PARAM, $action_obj);
        }
    }

    /**
     * 将 $_SERVER 变量中的 Header 提取出来转换为数组 K-V 形式
     */
    public static function convertHeaderFromGlobal(array $server): array
    {
        $headers = [];
        foreach ($server as $header => $value) {
            $header = strtolower($header);
            if (strpos($header, 'http_') === 0) {
                $string = '_' . str_replace('_', ' ', strtolower($header));
                $header = ltrim(str_replace(' ', '-', ucwords($string)), '_');
                $header = substr($header, 5);
                $headers[$header] = $value;
            }
        }
        return $headers;
    }
}
