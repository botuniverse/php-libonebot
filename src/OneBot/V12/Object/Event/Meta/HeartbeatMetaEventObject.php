<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Meta;

/**
 * OneBot 心跳事件对象
 */
class HeartbeatMetaEventObject extends MetaEventObject
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

    public function __construct($time = null)
    {
        parent::__construct('heartbeat', '', $time);
    }
}
