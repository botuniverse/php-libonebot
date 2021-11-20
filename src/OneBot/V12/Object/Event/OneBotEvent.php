<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event;

use DateTimeInterface;
use JsonSerializable;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\OneBot;

/**
 * OneBot 事件
 */
abstract class OneBotEvent implements JsonSerializable
{
    /**
     * 事件 ID
     *
     * @var string
     */
    public $id;

    /**
     * OneBot 实现名称
     *
     * @var string
     */
    public $impl;

    /**
     * OneBot 实现平台名称
     *
     * @var string
     */
    public $platform;

    /**
     * 机器人 ID
     *
     * @var string
     */
    public $self_id;

    /**
     * 事件发生事件
     *
     * @var int
     */
    public $time;

    /**
     * 事件类型
     *
     * @var string
     */
    public $type;

    /**
     * 事件详细类型
     *
     * @var string
     */
    public $detail_type;

    /**
     * 事件子类型
     *
     * @var string
     */
    public $sub_type;

    /**
     * @param string                     $type        事件类型
     * @param string                     $detail_type 事件详细类型
     * @param string                     $sub_type    事件子类型
     * @param null|DateTimeInterface|int $time        事件发生时间，可为DateTime对象或时间戳，不传或为null则使用当前时间
     *
     * @throws OneBotException
     */
    public function __construct(string $type, string $detail_type, string $sub_type, $time = null)
    {
        if (!in_array($type, ['meta', 'message', 'notice', 'request'])) {
            throw new OneBotException('事件类型错误：' . $type);
        }

        if ($time === null) {
            $time = time();
        } elseif ($time instanceof DateTimeInterface) {
            $time = $time->getTimestamp();
        }

        $ob = OneBot::getInstance();

        $this->id = ob_uuidgen();
        $this->impl = $ob->getImplementName();
        $this->platform = $ob->getPlatform();
        $this->self_id = $ob->getSelfId();
        $this->time = $time;
        $this->type = $type;
        $this->detail_type = $detail_type;
        $this->sub_type = $sub_type;
    }

    public function jsonSerialize()
    {
        return $this;
    }
}
