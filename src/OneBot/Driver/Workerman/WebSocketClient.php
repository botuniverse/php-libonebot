<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use Exception;
use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Http\WebSocket\FrameFactory;
use Psr\Http\Message\RequestInterface;
use Workerman\Connection\AsyncTcpConnection;

class WebSocketClient implements WebSocketClientInterface
{
    /**
     * @var int 连接状态，见 WebSocketClientInterface 中对 status 的常量定义
     */
    public $status;

    /**
     * @var AsyncTcpConnection Workerman 对应的连接维持对象
     */
    protected $connection;

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
        return $this->status <= 2;
    }

    public function setMessageCallback(callable $callable): WebSocketClientInterface
    {
        $this->status = $this->connection->getStatus();
        $this->connection->onMessage = function (AsyncTcpConnection $con, $data) use ($callable) {
            $frame = FrameFactory::createTextFrame($data);
            $callable($frame, $this);
        };
        return $this;
    }

    public function setCloseCallback(callable $callable): WebSocketClientInterface
    {
        $this->status = $this->connection->getStatus();
        $this->connection->onClose = function (AsyncTcpConnection $con) use ($callable) {
            $frame = FrameFactory::createCloseFrame(1000, '');
            $callable($frame, $this);
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

    public function getFd(): int
    {
        return $this->connection->id;
    }
}
