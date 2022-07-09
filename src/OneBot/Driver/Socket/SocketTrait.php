<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

use Generator;

trait SocketTrait
{
    /** @var WSServerSocketBase[] */
    protected $ws_socket = [];

    /** @var HttpServerSocketBase[] */
    protected $http_socket = [];

    /** @var HttpWebhookSocketBase[] */
    protected $http_webhook_socket = [];

    /** @var WSReverseSocketBase[] */
    protected $ws_reverse_socket = [];

    /* ======================== Getter by flags ======================== */

    /**
     * @return Generator|WSServerSocketBase[]
     */
    public function getWSServerSocketsByFlag(int $flag = 0): Generator
    {
        foreach ($this->ws_socket as $socket) {
            if ($socket->getFlag() === $flag) {
                yield $socket;
            }
        }
    }

    /**
     * @return Generator|HttpServerSocketBase[]
     */
    public function getHttpServerSocketsByFlag(int $flag = 0): Generator
    {
        foreach ($this->http_socket as $socket) {
            if ($socket->getFlag() === $flag) {
                yield $socket;
            }
        }
    }

    /**
     * @return Generator|HttpWebhookSocketBase[]
     */
    public function getHttpWebhookSocketsByFlag(int $flag = 0): Generator
    {
        foreach ($this->http_webhook_socket as $socket) {
            if ($socket->getFlag() === $flag) {
                yield $socket;
            }
        }
    }

    /**
     * @return Generator|WSReverseSocketBase[]
     */
    public function getWSReverseSocketsByFlag(int $flag = 0): Generator
    {
        foreach ($this->ws_reverse_socket as $socket) {
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
     * @return HttpWebhookSocketBase[]
     */
    public function getHttpWebhookSockets(): array
    {
        return $this->http_webhook_socket;
    }

    /**
     * @return WSReverseSocketBase[]
     */
    public function getWSReverseSockets(): array
    {
        return $this->ws_reverse_socket;
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

    public function addHttpWebhookSocket(HttpWebhookSocketBase $socket): void
    {
        $this->http_webhook_socket[] = $socket;
    }

    public function addWSReverseSocket(WSReverseSocketBase $socket): void
    {
        $this->ws_reverse_socket[] = $socket;
    }
}
