<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Notice\Group;

use DateTimeInterface;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Event\HasOperatorId;
use OneBot\V12\Object\Event\HasUserId;

/**
 * OneBot 群成员增加事件
 */
class GroupMemberIncreaseEvent extends GroupNoticeEvent
{
    use HasOperatorId;
    use HasUserId;

    /**
     * @param string                     $sub_type    事件子类型
     * @param string                     $group_id    群 ID
     * @param string                     $user_id     用户 ID
     * @param string                     $operator_id 操作者 ID
     * @param null|DateTimeInterface|int $time        事件发生时间，可为DateTime对象或时间戳，不传或为null则使用当前时间
     *
     * @throws OneBotException
     */
    public function __construct(string $sub_type, string $group_id, string $user_id, string $operator_id, $time = null)
    {
        parent::__construct('group_member_increase', $sub_type, $group_id, $time);

        $this->operator_id = $operator_id;
        $this->user_id = $user_id;
    }
}
