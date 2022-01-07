<?php

declare(strict_types=1);

namespace OneBot\V12\Driver;

use Psr\Http\Message\RequestInterface;

interface WebSocketClientInterface
{
    /**
     * Status initial.
     *
     * @var int
     */
    public const STATUS_INITIAL = 0;

    /**
     * Status connecting.
     *
     * @var int
     */
    public const STATUS_CONNECTING = 1;

    /**
     * Status connection established.
     *
     * @var int
     */
    public const STATUS_ESTABLISHED = 2;

    /**
     * Status closing.
     *
     * @var int
     */
    public const STATUS_CLOSING = 4;

    /**
     * Status closed.
     *
     * @var int
     */
    public const STATUS_CLOSED = 8;

    public function withRequest(RequestInterface $request): WebSocketClientInterface;

    /**
     * 调用此处方法后，即发起 HTTP 请求并连接 WebSocket，如果返回 false 则连接失败
     */
    public function connect(): bool;

    public function setMessageCallback(callable $callable): WebSocketClientInterface;

    public function setCloseCallback(callable $callable): WebSocketClientInterface;

    public function send($data): bool;
}