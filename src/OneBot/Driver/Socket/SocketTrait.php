<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

trait SocketTrait
{
    /** @var WSServerSocketBase[] */
    protected $ws_socket = [];

    /** @var HttpServerSocketBase[] */
    protected $http_socket = [];

    /** @var HttpClientSocketBase[] */
    protected $http_client_socket = [];

    /** @var WSClientSocketBase[] */
    protected $ws_client_socket = [];

    /* ======================== Getter by flags ======================== */

    /**
     * @return \Generator|WSServerSocketBase[]
     */
    public function getWSServerSocketsByFlag(int $flag = 0): \Generator
    {
        foreach ($this->ws_socket as $socket) {
            if ($socket->getFlag() === $flag) {
                yield $socket;
            }
        }
    }

    /**
     * @return \Generator|HttpServerSocketBase[]
     */
    public function getHttpServerSocketsByFlag(int $flag = 0): \Generator
    {
        foreach ($this->http_socket as $socket) {
            if ($socket->getFlag() === $flag) {
                yield $socket;
            }
        }
    }

    /**
     * @return \Generator|HttpClientSocketBase[]
     */
    public function getHttpWebhookSocketsByFlag(int $flag = 0): \Generator
    {
        foreach ($this->http_client_socket as $socket) {
            if ($socket->getFlag() === $flag) {
                yield $socket;
            }
        }
    }

    /**
     * @return \Generator|WSClientSocketBase[]
     */
    public function getWSReverseSocketsByFlag(int $flag = 0): \Generator
    {
        foreach ($this->ws_client_socket as $socket) {
            if ($socket->getFlag() === $flag) {
                yield $socket;
            }
        }
    }

    /* ======================== Getter for all ======================== */

    /**
     * @return WSServerSocketBase[]
     */
    public function getWSServerSockets(): array
    {
        return $this->ws_socket;
    }

    /**
     * @return HttpServerSocketBase[]
     */
    public function getHttpServerSockets(): array
    {
        return $this->http_socket;
    }

    /**
     * @return HttpClientSocketBase[]
     */
    public function getHttpWebhookSockets(): array
    {
        return $this->http_client_socket;
    }

    /**
     * @return WSClientSocketBase[]
     */
    public function getWSReverseSockets(): array
    {
        return $this->ws_client_socket;
    }

    /* ======================== Adder ======================== */

    public function addWSServerSocket(WSServerSocketBase $socket): void
    {
        $this->ws_socket[] = $socket;
    }

    public function addHttpServerSocket(HttpServerSocketBase $socket): void
    {
        $this->http_socket[] = $socket;
    }

    public function addHttpWebhookSocket(HttpClientSocketBase $socket): void
    {
        $this->http_client_socket[] = $socket;
    }

    public function addWSReverseSocket(WSClientSocketBase $socket): void
    {
        $this->ws_client_socket[] = $socket;
    }
}
