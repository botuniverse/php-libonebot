<?php

declare(strict_types=1);

namespace OneBot\Driver\Event\WebSocket;

use OneBot\Driver\Event\DriverEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class WebSocketOpenEvent extends DriverEvent
{
    /** @var ServerRequestInterface */
    protected $request;

    /** @var null|ResponseInterface */
    protected $response;

    /**
     * @var int
     */
    protected $fd;

    public function __construct(ServerRequestInterface $request, int $fd)
    {
        $this->request = $request;
        $this->fd = $fd;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @return $this
     */
    public function withResponse(?ResponseInterface $response): WebSocketOpenEvent
    {
        $this->response = $response;
        return $this;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
