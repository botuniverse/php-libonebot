<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Meta;

use OneBot\V12\Exception\OneBotException;

/**
 * OneBot 状态更新事件
 */
class StatusUpdateEvent extends MetaEvent
{
    /**
     * @var array OneBot 状态
     */
    public array $status;

    /**
     * @param array                       $status OneBot 状态，与 `get_status` 动作响应数据一致
     * @param null|\DateTimeInterface|int $time   事件发生时间，可为DateTime对象或时间戳，不传或为null则使用当前时间
     *
     * @throws OneBotException
     */
    public function __construct(array $status, $time = null)
    {
        $this->status = $status;
        parent::__construct('status_update', '', $time);
    }
}
