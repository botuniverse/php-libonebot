<?php

declare(strict_types=1);

namespace OneBot\Util;

use OneBot\V12\Action\ActionHandlerBase;
use OneBot\V12\Exception\OneBotFailureException;
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
    public static function getActionFuncName(ActionHandlerBase $handler, string $action): string
    {
        if (isset(ActionHandlerBase::$core_cache[$action])) {
            return ActionHandlerBase::$core_cache[$action];
        }

        if (isset(ActionHandlerBase::$ext_cache[$action])) {
            return ActionHandlerBase::$ext_cache[$action];
        }
        if (strpos($action, (OneBot::getInstance()->getPlatform() . '.')) === 0) {
            $func = self::separatorToCamel('ext_' . substr($action, strlen(OneBot::getInstance()->getPlatform()) + 1));
            if (method_exists($handler, $func)) {
                return ActionHandlerBase::$ext_cache[$action] = $func;
            }
        } else {
            $func = self::separatorToCamel('on_' . $action);
            if (method_exists($handler, $func)) {
                return ActionHandlerBase::$core_cache[$action] = $func;
            }
        }
        throw new OneBotFailureException(RetCode::UNSUPPORTED_ACTION);
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
