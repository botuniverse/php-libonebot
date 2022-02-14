<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use Psr\EventDispatcher\StoppableEventInterface;

abstract class DriverEvent implements Event, StoppableEventInterface
{
    /** @var bool 是否停止分发 */
    protected $propagationStopped = false;

    /** @var null|string 事件自定义名称 */
    protected static $custom_name;

    /**
     * 获取事件类型
     */
    public static function getName(): string
    {
        return static::$custom_name ?? static::class;
    }

    /**
     * {@inheritDoc}
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * 停止分发
     * 通过抛出异常
     *
     * @throws StopException
     */
    public function stopPropagation(): void
    {
        throw new StopException($this);
    }

    /**
     * 停止分发
     *
     * @internal
     */
    public function setPropagationStopped(): void
    {
        $this->propagationStopped = true;
    }
}
