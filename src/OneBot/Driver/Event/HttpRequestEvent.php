<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HttpRequestEvent extends DriverEvent
{
    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var null|mixed
     */
    protected $origin_request;

    /**
     * @var null|ResponseInterface
     */
    protected $response;

    public function __construct(ServerRequestInterface $request, $origin_request = null)
    {
        parent::__construct(Event::EVENT_HTTP_REQUEST);
        $this->request = $request;
        $this->origin_request = $origin_request;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function withResponse(ResponseInterface $response): HttpRequestEvent
    {
        $this->response = $response;
        return $this;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
