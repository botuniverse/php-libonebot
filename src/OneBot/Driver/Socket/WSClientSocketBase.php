<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

use OneBot\Driver\Interfaces\SocketInterface;
use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Driver\Interfaces\WebSocketInterface;

abstract class WSClientSocketBase implements SocketInterface, WebSocketInterface
{
    use SocketFlag;
    use SocketConfig;

    protected $url;

    protected $headers;

    protected $access_token;

    protected $reconnect_interval;

    protected WebSocketClientInterface $client;

    public function __construct(array $config)
    {
        $this->url = $config['url'];
        $this->headers = $config['headers'] ?? [];
        $this->access_token = $config['access_token'] ?? '';
        $this->reconnect_interval = $config['reconnect_interval'] ?? 5;
        $this->config = $config;
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

    public function send($data, $fd = null): bool
    {
        return $this->client->send($data);
    }
}
