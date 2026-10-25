<?php

declare(strict_types=1);

namespace OneBot\Driver\Stream;

use Choir\Http\Response;
use OneBot\Driver\Interfaces\StreamResponseInterface;
use OneBot\Driver\Interfaces\StreamSinkInterface;

/**
 * 开箱即用的 SSE（text/event-stream）响应
 *
 * 两种用法：
 *
 * 1) 只要标准 SSE 帧，用 send()/comment()：
 *    return SseResponse::create(function (StreamSinkInterface $sink, SseResponse $res) {
 *        foreach ($items as $i => $item) {
 *            if (!$res->send($sink, $item, 'token', (string) $i)) {
 *                break; // 客户端断开，立刻停止生产
 *            }
 *        }
 *    });
 *
 * 2) LLM / 上游 SSE 透传：原样转发上游字节（不要二次解析，省 CPU 也不会改变语义）：
 *    return SseResponse::create(function (StreamSinkInterface $sink) use ($chunks) {
 *        while (($chunk = $upstream->read()) !== null) {
 *            if (!$sink->write($chunk)) {
 *                break;
 *            }
 *        }
 *    });
 *
 * 注意：writer 在请求协程里执行，上游读取可以是阻塞写法（Swoole 会做协程调度）。
 */
class SseResponse extends Response implements StreamResponseInterface
{
    /** @var callable(StreamSinkInterface, SseResponse):void */
    private $writer;

    /** SSE 的默认响应头；同时挡住 nginx 之类的反向代理缓冲（否则前端会「一次性收到全部」） */
    public const DEFAULT_HEADERS = [
        'Content-Type' => 'text/event-stream; charset=utf-8',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Connection' => 'keep-alive',
        'X-Accel-Buffering' => 'no',
    ];

    /**
     * @param callable(StreamSinkInterface, SseResponse):void $writer
     * @param array<string, string|string[]>                  $headers 覆盖/追加的响应头
     */
    public function __construct(callable $writer, array $headers = [], int $status = 200)
    {
        parent::__construct($status, array_merge(self::DEFAULT_HEADERS, $headers));
        $this->writer = $writer;
    }

    /**
     * @param callable(StreamSinkInterface, SseResponse):void $writer
     * @param array<string, string|string[]>                  $headers
     */
    public static function create(callable $writer, array $headers = [], int $status = 200): self
    {
        return new self($writer, $headers, $status);
    }

    public function writeTo(StreamSinkInterface $sink): void
    {
        ($this->writer)($sink, $this);
    }

    /**
     * 写一帧标准 SSE 事件
     *
     * @param mixed       $data  数组/对象会被 json_encode；字符串原样（多行会自动拆成多个 data: 行）
     * @param string|null $event 事件名（前端 addEventListener($event, ...) 能单独接）
     * @param string|null $id    事件 ID（断线重连时浏览器会带 Last-Event-ID）
     * @param int|null    $retry 重连间隔（毫秒）
     *
     * @return bool 对端是否仍可写
     */
    public function send(StreamSinkInterface $sink, $data, ?string $event = null, ?string $id = null, ?int $retry = null): bool
    {
        return $sink->write(self::format($data, $event, $id, $retry));
    }

    /**
     * 写一行注释（SSE 规范里的 ": xxx"），常用于心跳保活，避免中间层按空闲超时掐连接
     */
    public function comment(StreamSinkInterface $sink, string $text = 'keep-alive'): bool
    {
        return $sink->write(': ' . $text . "\n\n");
    }

    /**
     * 把一条数据格式化成 SSE 帧
     *
     * @param mixed $data
     */
    public static function format($data, ?string $event = null, ?string $id = null, ?int $retry = null): string
    {
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($data === null) {
            $data = '';
        } else {
            $data = (string) $data;
        }
        $buffer = '';
        if ($id !== null && $id !== '') {
            $buffer .= 'id: ' . str_replace(["\r", "\n"], '', $id) . "\n";
        }
        if ($event !== null && $event !== '') {
            $buffer .= 'event: ' . str_replace(["\r", "\n"], '', $event) . "\n";
        }
        if ($retry !== null) {
            $buffer .= 'retry: ' . $retry . "\n";
        }
        // 多行数据必须每行一个 data:，否则浏览器只认第一行
        foreach (explode("\n", rtrim($data, "\r\n")) as $line) {
            $buffer .= 'data: ' . rtrim($line, "\r") . "\n";
        }

        return $buffer . "\n";
    }
}
