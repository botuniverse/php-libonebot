<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use Exception;
use OneBot\Driver\Interfaces\WebSocketClientInterface;
use Psr\Http\Message\RequestInterface;
use Workerman\Connection\AsyncTcpConnection;

class WebSocketClient implements WebSocketClientInterface
{
    /**
     * @var int
     */
    public $status;

    /** @var AsyncTcpConnection */
    protected $connection;

    public function __construct()
    {
    }

    /**
     * @throws Exception
     */
    public function withRequest(RequestInterface $request): WebSocketClientInterface
    {
        $this->connection = new AsyncTcpConnection('ws://' . $request->getUri()->getHost() . ':' . $request->getUri()->getPort());
        $this->connection->onConnect = function () use ($request) {
            $this->connection->send($request->getBody()->getContents());
            $this->status = self::STATUS_ESTABLISHED;
        };

        return $this;
    }

    public function connect(): bool
    {
        $this->connection->connect();
        $this->status = $this->connection->getStatus();
        return false;
    }

    public function setMessageCallback(callable $callable): WebSocketClientInterface
    {
        $this->status = $this->connection->getStatus();
        $this->connection->onMessage = function (AsyncTcpConnection $con, $data) use ($callable) {
            $callable($data, $this);
        };
        return $this;
    }

    public function setCloseCallback(callable $callable): WebSocketClientInterface
    {
        $this->status = $this->connection->getStatus();
        $this->connection->onClose = static function (AsyncTcpConnection $con) use ($callable) {
            $callable($con);
        };
        return $this;
    }

    public function send($data): bool
    {
        $this->connection->send($data);
        return true;
    }

    public function push($data): bool
    {
        return $this->send($data);
    }
}
