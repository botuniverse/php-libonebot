<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Message;

use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\MessageSegment;

/**
 * OneBot 私聊消息事件
 */
class PrivateMessageEvent extends MessageEvent
{
    /**
     * @param string                                 $user_id 用户 ID
     * @param MessageSegment|MessageSegment[]|string $message 消息内容
     * @param null|\DateTimeInterface|int            $time    事件发生时间，可为DateTime对象或时间戳，不传或为null则使用当前时间
     *
     * @throws OneBotException
     */
    public function __construct(string $user_id, $message, $time = null)
    {
        parent::__construct('private', $user_id, $message, $time);
    }
}
