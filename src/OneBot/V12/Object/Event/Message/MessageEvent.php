<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Message;

use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Event\HasMessageId;
use OneBot\V12\Object\Event\HasUserId;
use OneBot\V12\Object\Event\OneBotEvent;
use OneBot\V12\Object\MessageSegment;

/**
 * OneBot 消息事件
 *
 * @internal
 */
abstract class MessageEvent extends OneBotEvent
{
    use HasUserId;
    use HasMessageId;

    /**
     * 消息内容
     *
     * @var array<int, MessageSegment>|MessageSegment
     */
    public $message;

    /**
     * 消息内容的替代表示
     */
    public ?string $alt_message;

    /**
     * @param string                                 $detail_type 事件详细类型
     * @param string                                 $user_id     用户 ID
     * @param MessageSegment|MessageSegment[]|string $message     消息内容
     * @param null|\DateTimeInterface|int            $time        事件发生时间，可为DateTime对象或时间戳，不传或为null则使用当前时间
     *
     * @throws OneBotException
     */
    public function __construct(string $detail_type, string $user_id, $message, $time = null)
    {
        parent::__construct('message', $detail_type, '', $time);

        if (is_string($message)) {
            $this->alt_message = $message;
            $message = MessageSegment::text($message);
        }

        if ($message instanceof MessageSegment) {
            $this->message = [$message];
        } elseif (is_array($message)) {
            $this->message = $message;
        }

        $this->user_id = $user_id;
    }

    /**
     * 判断是否为匿名消息/来自匿名用户
     */
    public function isAnonymous(): bool
    {
        // TODO: 实现 isAnonymous 方法
        return false;
    }

    /**
     * 判断是否为系统消息
     */
    public function isSystem(): bool
    {
        // TODO: 实现 isSystem 方法
        return false;
    }
}
