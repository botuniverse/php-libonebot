<?php

declare(strict_types=1);

namespace OneBot\V12;

use OneBot\Util\Utils;
use OneBot\V12\Exception\OneBotException;
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
                    case ONEBOT_TYPE_ANY:
                        continue 2;
                    case ONEBOT_TYPE_STRING:
                        $func_name = 'is_string';
                        break;
                    case ONEBOT_TYPE_INT:
                        $func_name = 'is_int';
                        break;
                    case ONEBOT_TYPE_ARRAY:
                        $func_name = 'is_array';
                        break;
                    case ONEBOT_TYPE_FLOAT:
                        $func_name = 'is_float';
                        break;
                    case ONEBOT_TYPE_OBJECT:
                        $func_name = 'is_object';
                        break;
                    default:
                        throw new OneBotFailureException(RetCode::INTERNAL_HANDLER_ERROR, $action_obj, 'Unknown input validate type!');
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

    /**
     * 根据 OneBot 12 标准的规则，来验证事件的参数是否合规
     * 不合规将抛出 OneBotException 并附带相应提示语
     * （虽然可能这里的代码很长，但是这样运行速度快一点）
     *
     * @param  array           $data 数据数组
     * @throws OneBotException
     */
    public static function validateEventParams(array $data)
    {
        // 每个 OneBot 事件必须有这几个参数
        if (!isset($data['type'], $data['id'], $data['detail_type'], $data['sub_type'])) {
            throw new OneBotException('onebot 12 requires type, id, detail_type, sub_type');
        }
        // 除元事件（type = meta）外，其他事件必须拥有 self 字段
        if ($data['type'] !== 'meta' && !isset($data['self'])) {
            throw new OneBotException('onebot 12 requires self');
        }
        // 如果拥有 self 时，self 字段必须包含 platform 和 user_id 字段
        if (isset($data['self']) && (!isset($data['self']['platform']) || !isset($data['self']['user_id']))) {
            throw new OneBotException('onebot 12 requires self.platform and self.user_id');
        }
        switch ($data['type']) {
            case 'message':
                if (!isset($data['message'])) {
                    throw new OneBotException('onebot 12 requires message');
                }
                // 验证 MessageSegment
                self::validateMessageSegment($data['message']);
                switch ($data['detail_type']) {
                    case 'group':
                        if (!isset($data['group_id'], $data['user_id'], $data['message_id'], $data['alt_message'])) {
                            throw new OneBotException('group message must have group_id, user_id, message, message_id, alt_message');
                        }
                        break;
                    case 'private':
                        if (!isset($data['user_id'], $data['message_id'], $data['alt_message'])) {
                            throw new OneBotException('private message must have user_id, message, message_id, alt_message');
                        }
                        break;
                    case 'channel':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['user_id'], $data['message_id'])) {
                            throw new OneBotException('channel message must have channel_id, guild_id, user_id, message, message_id');
                        }
                        break;
                }
                break;
            case 'notice':
                switch ($data['detail_type']) {
                    case 'friend_increase':
                        if (!isset($data['user_id'])) {
                            throw new OneBotException('friend increase must have user_id');
                        }
                        break;
                    case 'friend_decrease':
                        if (!isset($data['user_id'])) {
                            throw new OneBotException('friend decrease must have user_id');
                        }
                        break;
                    case 'private_message_delete':
                        if (!isset($data['user_id'], $data['message_id'])) {
                            throw new OneBotException('private message delete must have user_id, message_id');
                        }
                        break;
                    case 'group_member_increase':
                        if (!isset($data['group_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('group member increase must have group_id, user_id, operator_id');
                        }
                        break;
                    case 'group_member_decrease':
                        if (!isset($data['group_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('group member decrease must have group_id, user_id, operator_id');
                        }
                        break;
                    case 'group_message_delete':
                        if (!isset($data['group_id'], $data['user_id'], $data['message_id'], $data['operator_id'])) {
                            throw new OneBotException('group message delete must have group_id, user_id, message_id');
                        }
                        break;
                    case 'guild_member_increase':
                        if (!isset($data['guild_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('guild member increase must have guild_id, user_id, operator_id');
                        }
                        break;
                    case 'guild_member_decrease':
                        if (!isset($data['guild_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('guild member decrease must have guild_id, user_id, operator_id');
                        }
                        break;
                    case 'channel_member_increase':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('channel member increase must have channel_id, guild_id, user_id, operator_id');
                        }
                        break;
                    case 'channel_member_decrease':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('channel member decrease must have channel_id, guild_id, user_id, operator_id');
                        }
                        break;
                    case 'channel_message_delete':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['user_id'], $data['message_id'], $data['operator_id'])) {
                            throw new OneBotException('channel message delete must have channel_id, guild_id, user_id, message_id, operator_id');
                        }
                        break;
                    case 'channel_create':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['operator_id'])) {
                            throw new OneBotException('channel create must have channel_id, guild_id, operator_id');
                        }
                        break;
                    case 'channel_delete':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['operator_id'])) {
                            throw new OneBotException('channel delete must have channel_id, guild_id, operator_id');
                        }
                        break;
                }
                break;
            case 'request':
            case 'meta':
                break;
            default:
                throw new OneBotException('unknown event type');
        }
    }

    private static function validateExist(Action $action_obj, $k): bool
    {
        return isset($action_obj->params[$k]);
    }
}
