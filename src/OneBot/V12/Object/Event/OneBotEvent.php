<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event;

use ArrayIterator;
use DateTimeInterface;
use IteratorAggregate;
use JsonSerializable;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\OneBot;
use ReturnTypeWillChange;

/**
 * OneBot 事件
 *
 * @internal
 */
abstract class OneBotEvent implements JsonSerializable, IteratorAggregate
{
    /** @var string 事件ID */
    public $id;

    /** @var string OneBot实现名称 */
    public $impl;

    /** @var string OneBot实现平台名称 */
    public $platform;

    /** @var string 机器人ID */
    public $self_id;

    /** @var int 事件发生时间 */
    public $time;

    /** @var string 事件类型 */
    public $type;

    /** @var string 事件详细类型 */
    public $detail_type;

    /** @var string 事件子类型 */
    public $sub_type;

    /** @var array 扩展数据 */
    private $extended_data = [];

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
            /** @var false|int $tmpTime */
            $tmpTime = $time->getTimestamp();
            // 在 PHP 8.0 前，DateTime::getTimestamp() 会在失败时返回 false
            if ($tmpTime === false) {
                throw new OneBotException('传入的时间无法转换为时间戳');
            }
            $time = $tmpTime;
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

    /**
     * 获取扩展数据
     */
    public function getExtendedData(): array
    {
        return $this->extended_data;
    }

    /**
     * 设置扩展数据
     */
    public function setExtendedData(array $extended_data): self
    {
        $this->extended_data = [];
        foreach ($extended_data as $key => $value) {
            $this->extended_data["{$this->platform}.{$key}"] = $value;
        }
        return $this;
    }

    /**
     * 获取扩展数据项
     *
     * @return null|mixed
     */
    public function getExtendedDatum(string $key)
    {
        return $this->extended_data["{$this->platform}.{$key}"] ?? null;
    }

    /**
     * 设置扩展数据项
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function setExtendedDatum(string $key, $value): self
    {
        $this->extended_data["{$this->platform}.{$key}"] = $value;
        return $this;
    }

    /**
     * 删除扩展数据项
     *
     * @return $this
     */
    public function unsetExtendedDatum(string $key): self
    {
        unset($this->extended_data["{$this->platform}.{$key}"]);
        return $this;
    }

    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this as $k => $v) {
            if ($k === 'extended_data') {
                continue;
            }
            $data[$k] = $v;
            if ($k === 'detail_type') {
                $data[$k] = empty($this->extended_data) ? $this->detail_type : "{$this->impl}.{$this->detail_type}";
            }
        }
        return $data;
    }

    /**
     * @noinspection PhpLanguageLevelInspection
     */
    #[ReturnTypeWillChange]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this);
    }
}
