<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use Psr\EventDispatcher\StoppableEventInterface;

abstract class DriverEvent implements Event, StoppableEventInterface
{
    /** @var bool 是否停止分发 */
    protected $propagationStopped = false;

    /** @var string 事件类型 */
    protected $type = Event::EVENT_UNKNOWN;

    /**
     * 创建一个新的驱动事件
     *
     * @param string $type 事件类型
     */
    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * 获取事件类型
     */
    public function getType(): string
    {
        return $this->type;
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
