<?php

declare(strict_types=1);

namespace OneBot\Driver\Event\WebSocket;

use OneBot\Driver\Event\DriverEvent;

class WebSocketCloseEvent extends DriverEvent
{
    protected $fd;

    public function __construct(int $fd)
    {
        $this->fd = $fd;
    }

    public function getFd(): int
    {
        return $this->fd;
    }
}
