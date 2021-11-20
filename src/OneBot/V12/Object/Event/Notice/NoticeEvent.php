<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Notice;

use DateTimeInterface;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Event\HasUserId;
use OneBot\V12\Object\Event\OneBotEvent;

/**
 * OneBot 通知事件
 */
abstract class NoticeEvent extends OneBotEvent
{
    use HasUserId;

    /**
     * @param string                     $detail_type 事件详细类型
     * @param string                     $sub_type    事件子类型
     * @param string                     $user_id     用户 ID
     * @param null|DateTimeInterface|int $time        事件发生时间，可为DateTime对象或时间戳，不传或为null则使用当前时间
     *
     * @throws OneBotException
     */
    public function __construct(string $detail_type, string $sub_type, string $user_id, $time = null)
    {
        parent::__construct('notice', $detail_type, $sub_type, $time);

        $this->user_id = $user_id;
    }
}
