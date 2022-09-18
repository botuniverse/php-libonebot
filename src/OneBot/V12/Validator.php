<?php

declare(strict_types=1);

namespace OneBot\V12;

use OneBot\Util\Utils;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\Action;

class Validator
{
    /**
     * 验证传入的消息段是否合法
     * @param  array|mixed            $message
     * @throws OneBotFailureException
     */
    public static function validateMessageSegment($message): void
    {
        if (!is_array($message)) {
            throw new OneBotFailureException(RetCode::BAD_SEGMENT_DATA);
        }
        foreach ($message as $v) {
            if (!isset($v['type']) || !isset($v['data'])) {
                throw new OneBotFailureException(RetCode::BAD_SEGMENT_DATA);
            }
            if ($v['type'] === 'text' && !is_string($v['data']['text'] ?? null)) {
                throw new OneBotFailureException(RetCode::BAD_SEGMENT_DATA);
            }
            if ($v['type'] === 'image' && !isset($v['data']['file_id'])) {
                throw new OneBotFailureException(RetCode::BAD_SEGMENT_DATA);
            }
        }
    }

    /**
     * 用于验证动作对象中的参数验证
     *
     * 如果验证失败，直接抛出 BAD_PARAM 异常。
     *
     * $array 为验证方式，目前支持两种验证：
     * 1. 如果 k => true，则验证 param 是否存在 k。
     * 2. 如果 k => {list}，则在 1 的基础上验证参数 k 是否是给定 list 中的一种。
     * 3. 如果 k => int, 则根据 int 对应规则进行验证。
     *
     * @throws OneBotFailureException
     */
    public static function validateParamsByAction(Action $action_obj, array $array): void
    {
        $valid = true;
        foreach ($array as $k => $v) {
            if (!($valid = self::validateExist($action_obj, $k))) {
                break;
            }
            if ($v === true) {
                continue;
            }
            if (is_int($v)) {
                switch ($v) {
                    case ONEBOT_TYPE_ANY:       continue 2;
                    case ONEBOT_TYPE_STRING:    $func_name = 'is_string'; break;
                    case ONEBOT_TYPE_INT:       $func_name = 'is_int'; break;
                    case ONEBOT_TYPE_ARRAY:     $func_name = 'is_array'; break;
                    case ONEBOT_TYPE_FLOAT:     $func_name = 'is_float'; break;
                    case ONEBOT_TYPE_OBJECT:    $func_name = 'is_object'; break;
                    default:                    throw new OneBotFailureException(RetCode::INTERNAL_HANDLER_ERROR, $action_obj, 'Unknown input validate type!');
                }
                if (!($valid = $func_name($action_obj->params[$k]))) {
                    break;
                }
            } elseif (is_array($v) && !Utils::isAssocArray($v)) {
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

    public static function validateHttpUrl(string $url): void
    {
        $parse = parse_url($url);
        if (!isset($parse['scheme']) || $parse['scheme'] !== 'http' && $parse['scheme'] !== 'https') {
            throw new OneBotFailureException(RetCode::NETWORK_ERROR);
        }
    }

    private static function validateExist(Action $action_obj, $k): bool
    {
        return isset($action_obj->params[$k]);
    }
}
