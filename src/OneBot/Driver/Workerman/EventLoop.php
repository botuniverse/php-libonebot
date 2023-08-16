<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use OneBot\Driver\DriverEventLoopBase;
use Workerman\Events\EventInterface;
use Workerman\Timer;

class EventLoop extends DriverEventLoopBase
{
    public function addTimer(int $ms, callable $callable, int $times = 1, array $arguments = []): int
    {
        $timer_count = 0;
        return Timer::add($ms / 1000, function () use (&$timer_id, &$timer_count, $callable, $times, $arguments) {
            if ($times > 0) {
                ++$timer_count;
                if ($timer_count > $times) {
                    Timer::del($timer_id);
                    return;
                }
            }
            $callable($timer_id, ...$arguments);
        }, $arguments);
    }

    public function clearTimer(int $timer_id)
    {
        Timer::del($timer_id);
    }

    public function addReadEvent($fd, callable $callable)
    {
        Worker::getEventLoop()->add($fd, EventInterface::EV_READ, $callable);
    }

    public function delReadEvent($fd)
    {
        Worker::getEventLoop()->del($fd, EventInterface::EV_READ);
    }

    public function addWriteEvent($fd, callable $callable)
    {
        Worker::getEventLoop()->add($fd, EventInterface::EV_WRITE, $callable);
    }

    public function delWriteEvent($fd)
    {
        Worker::getEventLoop()->del($fd, EventInterface::EV_WRITE);
    }

    public function clearAllTimer()
    {
        Timer::delAll();
    }
}
