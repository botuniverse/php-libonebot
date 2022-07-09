<?php

declare(strict_types=1);

namespace OneBot\Util;

use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\Action;
use OneBot\V12\RetCode;

class Validator
{
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
     *
     * @throws OneBotFailureException
     */
    public static function validateParamsByAction(Action $action_obj, array $array): void
    {
        $valid = true;
        foreach ($array as $k => $v) {
            if ($v === true) {
                if (!isset($action_obj->params[$k])) {
                    $valid = false;
                    break;
                }
            } elseif (!Utils::isAssocArray($v)) {
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
}
