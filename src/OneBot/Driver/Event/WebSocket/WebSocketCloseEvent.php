<?php

declare(strict_types=1);

namespace OneBot\Driver\Event\WebSocket;

use OneBot\Driver\Event\DriverEvent;
use OneBot\Driver\Event\Event;

class WebSocketCloseEvent extends DriverEvent
{
    protected $fd;

    public function __construct(int $fd)
    {
        parent::__construct(Event::EVENT_WEBSOCKET_CLOSE);
        $this->fd = $fd;
    }
}
