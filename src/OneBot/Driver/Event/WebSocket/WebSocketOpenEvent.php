<?php

declare(strict_types=1);

namespace OneBot\Driver\Event\WebSocket;

use OneBot\Driver\Event\DriverEvent;
use OneBot\Driver\Event\Event;
use Psr\Http\Message\ServerRequestInterface;

class WebSocketOpenEvent extends DriverEvent
{
    protected $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
        parent::__construct(Event::EVENT_WEBSOCKET_OPEN);
    }
}
