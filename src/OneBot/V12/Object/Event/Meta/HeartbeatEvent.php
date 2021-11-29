<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Meta;

use DateTimeInterface;
use OneBot\V12\Exception\OneBotException;

/**
 * OneBot 心跳事件
 */
class HeartbeatEvent extends MetaEvent
{
    /**
     * 与下次心跳的间隔，单位毫秒
     *
     * @var int
     */
    public $interval;

    /**
     * OneBot 状态
     *
     * @var array
     */
    public $status;

    /**
     * @param null|DateTimeInterface|int $time 事件发生时间，可为DateTime对象或时间戳，不传或为null则使用当前时间
     *
     * @throws OneBotException
     */
    public function __construct($time = null)
    {
        parent::__construct('heartbeat', '', $time);
    }
}
