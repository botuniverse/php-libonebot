<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole;

use OneBot\Driver\DriverEventLoopBase;
use Swoole\Event;
use Swoole\Timer;

class EventLoop extends DriverEventLoopBase
{
    public function addReadEvent($fd, callable $callable)
    {
        Event::add($fd, $callable);
    }

    public function delReadEvent($fd)
    {
        Event::del($fd);
    }

    public function addWriteEvent($fd, callable $callable)
    {
        Event::add($fd, null, $callable);
    }

    public function delWriteEvent($fd)
    {
        Event::del($fd);
    }

    public function addTimer(int $ms, callable $callable, int $times = 1, array $arguments = []): int
    {
        $timer_count = 0;
        return Timer::tick($ms, function ($timer_id, ...$params) use (&$timer_count, $callable, $times) {
            if ($times > 0) {
                ++$timer_count;
                if ($timer_count > $times) {
                    Timer::clear($timer_id);
                    return;
                }
            }
            $callable($timer_id, ...$params);
        }, ...$arguments);
    }

    public function clearTimer(int $timer_id)
    {
        Timer::clear($timer_id);
    }

    public function clearAllTimer()
    {
        Timer::clearAll();
    }
}
