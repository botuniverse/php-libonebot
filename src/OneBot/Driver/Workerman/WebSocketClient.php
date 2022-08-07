<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use Exception;
use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Http\HttpFactory;
use OneBot\Http\WebSocket\FrameFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
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
     * @var RequestInterface 请求对象
     */
    protected $request;

    /**
     * @var callable 消息回调
     */
    protected $on_message;

    /**
     * @var callable 关闭回调
     */
    protected $on_close;

    /**
     * 通过地址来创建一个 WebSocket 连接
     *
     * 支持 UriInterface 接口的 PSR 对象，也支持直接传入一个带 Scheme 的
     *
     * @param  string|UriInterface $address 地址
     * @param  array               $header  请求头
     * @throws Exception
     */
    public static function createFromAddress($address, array $header = []): WebSocketClientInterface
    {
        return (new self())->withRequest(HttpFactory::getInstance()->createRequest('GET', $address, $header));
    }

    /**
     * @throws Exception
     */
    public function withRequest(RequestInterface $request): WebSocketClientInterface
    {
        // 通过 AsyncTcpConnection 建立连接
        $this->connection = new AsyncTcpConnection('ws://' . $request->getUri()->getHost() . ':' . $request->getUri()->getPort());
        // 通过 walkor 的隐藏魔法（无语了），设置请求的 Header。因为 PSR 的 Request 对象返回 Headers 是数组形式的，我们不需要重复的 Header 只取一个就行
        /* @phpstan-ignore-next-line */
        $this->connection->headers = array_map(function ($x) {
            return $x[0];
        }, $request->getHeaders());
        // 如果连接建立后，可以通，则把 Request 请求中的请求包体以 WebSocket Message 发送给目标 Server。
        $this->connection->onConnect = function () use ($request) {
            $this->connection->send($request->getBody()->getContents());
            $this->status = self::STATUS_ESTABLISHED;
        };
        $this->request = $request;

        return $this;
    }

    public function connect(): bool
    {
        $this->connection->connect();
        $this->status = $this->connection->getStatus();
        return $this->status <= 2;
    }

    /**
     * @throws Exception
     */
    public function reconnect(): bool
    {
        return $this->withRequest($this->request)->setMessageCallback($this->on_message)->setCloseCallback($this->on_close)->connect();
    }

    public function setMessageCallback($callable): WebSocketClientInterface
    {
        $this->status = $this->connection->getStatus();
        $this->on_message = $callable;
        $this->connection->onMessage = function (AsyncTcpConnection $con, $data) use ($callable) {
            $frame = FrameFactory::createTextFrame($data);
            $callable($frame, $this);
        };
        return $this;
    }

    public function setCloseCallback($callable): WebSocketClientInterface
    {
        $this->status = $this->connection->getStatus();
        $this->on_close = $callable;
        $this->connection->onClose = function (AsyncTcpConnection $con) use ($callable) {
            $frame = FrameFactory::createCloseFrame(1000, '');
            $con->close();
            $callable($frame, $this, $con->getStatus(false));
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

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_ESTABLISHED;
    }
}
