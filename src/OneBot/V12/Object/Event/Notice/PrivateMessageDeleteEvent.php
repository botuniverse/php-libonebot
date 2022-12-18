<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Notice;

use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Event\HasMessageId;
use OneBot\V12\Object\Event\HasUserId;

/**
 * OneBot 私聊消息删除事件
 */
class PrivateMessageDeleteEvent extends NoticeEvent
{
    use HasMessageId;
    use HasUserId;

    /**
     * @param string                      $sub_type   事件子类型
     * @param string                      $message_id 消息 ID
     * @param string                      $user_id    用户 ID
     * @param null|\DateTimeInterface|int $time       事件发生时间，可为DateTime对象或时间戳，不传或为null则使用当前时间
     *
     * @throws OneBotException
     */
    public function __construct(string $sub_type, string $message_id, string $user_id, $time = null)
    {
        parent::__construct('private_message_delete', $sub_type, $time);
        $this->user_id = $user_id;
        $this->message_id = $message_id;
    }
}
