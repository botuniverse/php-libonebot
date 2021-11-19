<?php

declare(strict_types=1);

namespace OneBot\V12\Object;

use JsonSerializable;
use MessagePack\CanBePacked;
use MessagePack\MessagePack;
use MessagePack\Packer;
use OneBot\V12\Exception\OneBotException;

/**
 * OneBot 事件对象
 */
abstract class EventObject implements JsonSerializable, CanBePacked
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
     * 构造事件对象
     *
     * @param string                      $type        事件类型
     * @param string                      $detail_type 事件详细类型
     * @param string                      $sub_type    事件子类型
     * @param null|\DateTimeInterface|int $time        事件发生时间，不传或为null则使用当前时间
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
        }
        if ($time instanceof \DateTimeInterface) {
            $time = $time->getTimestamp();
        }

        $this->id = 0; // TODO: 自动生成事件 ID
        $this->impl = 'internal'; // TODO: 自动读取 OB 实现名称
        $this->platform = 'internal'; // TODO: 自动读取实现平台名称
        $this->self_id = 'internal'; // TODO: 自动读取机器人 ID
        $this->time = $time;
        $this->type = $type;
        $this->detail_type = $detail_type;
        $this->sub_type = $sub_type;
    }

    public function jsonSerialize(): string
    {
        return json_encode($this);
    }

    public function pack(Packer $packer): string
    {
        return MessagePack::pack($this);
    }
}
