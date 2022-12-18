<?php

declare(strict_types=1);

namespace OneBot\Driver\Choir;

class EventLoopWrapper extends \OneBot\Driver\DriverEventLoopBase
{
    /**
     * {@inheritDoc}
     */
    public function addReadEvent($fd, callable $callable)
    {
        // TODO: Implement addReadEvent() method.
    }

    /**
     * {@inheritDoc}
     */
    public function delReadEvent($fd)
    {
        // TODO: Implement delReadEvent() method.
    }

    /**
     * {@inheritDoc}
     */
    public function addWriteEvent($fd, callable $callable)
    {
        // TODO: Implement addWriteEvent() method.
    }

    /**
     * {@inheritDoc}
     */
    public function delWriteEvent($fd)
    {
        // TODO: Implement delWriteEvent() method.
    }

    /**
     * {@inheritDoc}
     */
    public function addTimer(int $ms, callable $callable, int $times = 1, array $arguments = []): int
    {
        // TODO: Implement addTimer() method.
        return -1;
    }

    /**
     * {@inheritDoc}
     */
    public function clearTimer(int $timer_id)
    {
        // TODO: Implement clearTimer() method.
    }
}
