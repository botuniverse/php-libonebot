<?php

declare(strict_types=1);

namespace OneBot\V12\Driver;

use Psr\Http\Message\RequestInterface;

interface WebSocketClientInterface
{
    public function withRequest(RequestInterface $request): WebSocketClientInterface;

    /**
     * 调用此处方法后，即发起 HTTP 请求并连接 WebSocket，如果返回 false 则连接失败
     */
    public function create(): bool;

    public function setMessageCallback(callable $callable): WebSocketClientInterface;

    public function setCloseCallback(callable $callable): WebSocketClientInterface;
}
