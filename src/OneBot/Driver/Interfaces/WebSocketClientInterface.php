<?php

declare(strict_types=1);

namespace OneBot\Driver\Interfaces;

use Choir\WebSocket\FrameInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

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

    /**
     * 通过地址和 Headers 快读返回一个 WebSocketClient 对象.
     *
     * @param  string|UriInterface      $address 地址 Uri 对象或地址字符串
     * @param  array                    $header  请求的 Headers 数组
     * @return WebSocketClientInterface 返回一个 WebSocketClient 对象
     */
    public static function createFromAddress($address, array $header = []): WebSocketClientInterface;

    /**
     * 通过 HTTP 请求对象创建 WebSocket 连接对象
     *
     * @param RequestInterface $request 请求对象
     */
    public function withRequest(RequestInterface $request): WebSocketClientInterface;

    /**
     * 调用此处方法后，即发起 HTTP 请求并连接 WebSocket，如果返回 false 则连接失败
     */
    public function connect(): bool;

    /**
     * 调用此方法后，重新发起一次相同的 HTTP 请求并连接 WebSocket，返回内容同 connect()
     */
    public function reconnect(): bool;

    /**
     * 设置 WebSocket 收到消息的回调函数
     *
     * @param callable|\Closure $callable 回调函数
     */
    public function setMessageCallback($callable): WebSocketClientInterface;

    /**
     * 设置 WebSocket 连接关闭时触发的回调函数
     *
     * @param callable|\Closure $callable 回调函数
     */
    public function setCloseCallback($callable): WebSocketClientInterface;

    /**
     * 发送 WebSocket 信息
     *
     * @param  FrameInterface|string $data 数据包体，可以为字符串，也可以直接传入 Frame 对象
     * @return bool                  返回是否成功发送
     */
    public function send($data): bool;

    /**
     * 发送 WebSocket 消息，同 send()
     *
     * @param  FrameInterface|string $data 数据包体，同 send()
     * @return bool                  返回是否成功发送
     */
    public function push($data): bool;

    /**
     * 获取当前 Client 的连接 ID
     *
     * @return int 返回连接 ID
     */
    public function getFd(): int;

    /**
     * 检测当前 Client 是否已连接
     */
    public function isConnected(): bool;
}
