<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use Psr\EventDispatcher\StoppableEventInterface;

abstract class DriverEvent implements Event, StoppableEventInterface
{
    /** @var bool 是否停止分发 */
    protected bool $propagation_stopped = false;

    protected array $socket_config = [];

    /** @var null|string 事件自定义名称 */
    protected static ?string $custom_name;

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
        return $this->propagation_stopped;
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
        $this->propagation_stopped = true;
    }

    public function getSocketFlag(): int
    {
        return $this->socket_config['flag'] ?? 1;
    }

    public function getSocketConfig(): array
    {
        return $this->socket_config;
    }

    public function setSocketConfig(array $socket_config): void
    {
        $this->socket_config = $socket_config;
    }
}
