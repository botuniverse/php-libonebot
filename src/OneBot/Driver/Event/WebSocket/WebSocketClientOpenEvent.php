<?php

declare(strict_types=1);

namespace OneBot\Driver\Event\WebSocket;

use OneBot\Driver\Event\DriverEvent;

class WebSocketClientOpenEvent extends DriverEvent
{
    private int $fd;

    private $send_callback;

    public function __construct(int $fd, callable $send_callback)
    {
        $this->fd = $fd;
        $this->send_callback = $send_callback;
    }

    public function getFd(): int
    {
        return $this->fd;
    }

    public function send($data)
    {
        return call_user_func($this->send_callback, $this->fd, $data);
    }
}
