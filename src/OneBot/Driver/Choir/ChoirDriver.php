<?php

declare(strict_types=1);

namespace OneBot\Driver\Choir;

use Choir\Http\Client\CurlClient;
use Choir\Http\Client\StreamClient;
use OneBot\Driver\Driver;
use OneBot\Driver\DriverEventLoopBase;
use OneBot\Driver\Socket\HttpClientSocketBase;
use OneBot\Util\Singleton;

class ChoirDriver extends Driver
{
    use Singleton;

    public const SUPPORTED_CLIENTS = [
        CurlClient::class,
        StreamClient::class,
    ];

    /**
     * @throws \Exception
     */
    public function __construct(array $params = [])
    {
        if (static::$instance !== null) {
            throw new \Exception('不能重复初始化');
        }
        static::$instance = $this;
        parent::__construct($params);
    }

    /**
     * {@inheritDoc}
     */
    public function run(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'choir';
    }

    /**
     * {@inheritDoc}
     */
    public function getEventLoop(): DriverEventLoopBase
    {
        return EventLoopWrapper::getInstance();
    }

    /**
     * {@inheritDoc}
     */
    public function initWSReverseClients(array $headers = [])
    {
        foreach ($this->ws_client_socket as $v) {
            $v->setClient(WebSocketClient::createFromAddress($v->getUrl(), array_merge($headers, $v->getHeaders())));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function createHttpClientSocket(array $config): HttpClientSocketBase
    {
        // TODO: Implement createHttpClientSocket() method.
        throw new \Exception('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    protected function initInternalDriverClasses(?array $http, ?array $http_webhook, ?array $ws, ?array $ws_reverse): array
    {
        // TODO: Implement initInternalDriverClasses() method.
        return [];
    }
}
