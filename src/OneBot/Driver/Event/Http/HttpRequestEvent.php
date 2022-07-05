<?php

declare(strict_types=1);

namespace OneBot\Driver\Event\Http;

use OneBot\Driver\Event\DriverEvent;
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

    /**
     * @var null|callable
     */
    protected $error_handler;

    /**
     * @var null|callable
     */
    protected $async_send_callable;

    /**
     * @var bool
     */
    protected $async_send = false;

    public function __construct(ServerRequestInterface $request, $origin_request = null)
    {
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

    public function setErrorHandler(callable $callable)
    {
        $this->error_handler = $callable;
    }

    public function getErrorHandler(): callable
    {
        return $this->error_handler;
    }

    public function withAsyncResponseCallable(callable $callable): HttpRequestEvent
    {
        $this->async_send_callable = $callable;
        return $this;
    }

    public function setAsyncSend(bool $async_send = true): void
    {
        $this->async_send = $async_send;
    }

    public function getAsyncSendCallable(): ?callable
    {
        return $this->async_send_callable;
    }
}
