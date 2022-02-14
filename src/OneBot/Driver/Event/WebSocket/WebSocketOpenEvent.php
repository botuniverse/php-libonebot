<?php

declare(strict_types=1);

namespace OneBot\Driver\Event\WebSocket;

use OneBot\Driver\Event\DriverEvent;
use Psr\Http\Message\ServerRequestInterface;

class WebSocketOpenEvent extends DriverEvent
{
    protected $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }
}
