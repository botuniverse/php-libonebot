<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Notice;

use DateTimeInterface;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Event\HasUserId;

/**
 * OneBot 好友减少事件
 */
class FriendDecreaseEvent extends NoticeEvent
{
    use HasUserId;

    /**
     * @param string                     $sub_type 事件子类型
     * @param string                     $user_id  用户 ID
     * @param null|DateTimeInterface|int $time     事件发生时间，可为DateTime对象或时间戳，不传或为null则使用当前时间
     *
     * @throws OneBotException
     */
    public function __construct(string $sub_type, string $user_id, $time = null)
    {
        parent::__construct('friend_decrease', $sub_type, $time);

        $this->user_id = $user_id;
    }
}
