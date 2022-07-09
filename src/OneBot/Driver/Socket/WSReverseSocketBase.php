<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

use OneBot\Driver\Interfaces\SocketInterface;
use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Driver\Interfaces\WebSocketInterface;
use OneBot\Http\WebSocket\FrameInterface;

abstract class WSReverseSocketBase implements SocketInterface, WebSocketInterface
{
    use SocketFlag;

    protected $url;

    protected $headers;

    protected $access_token;

    protected $reconnect_interval;

    /**
     * @var WebSocketClientInterface
     */
    protected $client;

    public function __construct(string $url, array $headers = [], string $access_token = '', int $reconnect_interval = 5)
    {
        $this->url = $url;
        $this->headers = $headers;
        $this->access_token = $access_token;
        $this->reconnect_interval = $reconnect_interval;
    }

    public function setClient(WebSocketClientInterface $client)
    {
        $this->client = $client;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getAccessToken(): string
    {
        return $this->access_token;
    }

    public function getReconnectInterval(): int
    {
        return $this->reconnect_interval;
    }

    public function getClient(): WebSocketClientInterface
    {
        return $this->client;
    }

    public function send(FrameInterface $data, $id = null): bool
    {
        return $this->client->send($data);
    }
}
