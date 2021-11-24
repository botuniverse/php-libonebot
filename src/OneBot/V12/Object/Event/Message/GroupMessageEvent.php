<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Message;

use DateTimeInterface;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Event\HasGroupId;
use OneBot\V12\Object\MessageSegment;

/**
 * 群消息事件
 */
class GroupMessageEvent extends MessageEvent
{
    use HasGroupId;

    /**
     * @param string                                 $group_id 群 ID
     * @param string                                 $user_id  用户 ID
     * @param MessageSegment|MessageSegment[]|string $message  消息内容
     * @param null|DateTimeInterface|int             $time     事件发生时间，可为DateTime对象或时间戳，不传或为null则使用当前时间
     *
     * @throws OneBotException
     */
    public function __construct(string $group_id, string $user_id, $message, $time = null)
    {
        parent::__construct('group', $user_id, $message, $time);

        $this->group_id = $group_id;
    }
}
