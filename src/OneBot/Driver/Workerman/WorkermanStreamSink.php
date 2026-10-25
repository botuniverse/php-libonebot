<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use OneBot\Driver\Interfaces\StreamSinkInterface;
use Workerman\Connection\TcpConnection;

/**
 * Workerman 驱动的流式出水口
 *
 * Workerman 不会替我们做 chunked 编码（它只会在 send($response) 时一次性写出完整响应），
 * 所以这里手写 HTTP/1.1 chunked：首包发响应头，之后每块 "长度(hex)\r\n数据\r\n"，
 * 结束时发终止块 "0\r\n\r\n" 并关闭连接。
 *
 * 注意：Workerman 是多进程 + 单线程模型，没有 Swoole 那样的协程调度。
 * 一条 SSE 会独占一个 worker 进程，并发 SSE 数受 worker 数限制；
 * 需要更高并发请用 Swoole 驱动。
 */
class WorkermanStreamSink implements StreamSinkInterface
{
    private const REASONS = [
        200 => 'OK', 201 => 'Created', 204 => 'No Content', 301 => 'Moved Permanently',
        302 => 'Found', 304 => 'Not Modified', 400 => 'Bad Request', 401 => 'Unauthorized',
        403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed',
        500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable',
    ];

    private TcpConnection $connection;

    private int $status;

    /** @var array<string, string|string[]> */
    private array $headers;

    private bool $headers_sent = false;

    private bool $closed = false;

    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(TcpConnection $connection, int $status = 200, array $headers = [])
    {
        $this->connection = $connection;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function write(string $chunk): bool
    {
        if ($this->closed) {
            return false;
        }
        if ($chunk === '') {
            return true;
        }
        if (!$this->headers_sent && !$this->sendHeaders()) {
            return false;
        }
        $payload = dechex(strlen($chunk)) . "\r\n" . $chunk . "\r\n";
        try {
            $ok = $this->connection->send($payload, true);
        } catch (\Throwable $e) {
            $ok = false;
        }
        if ($ok === false) {
            $this->closed = true;
            return false;
        }

        return true;
    }

    public function end(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        try {
            // 一个 chunk 都没发过时也要把响应头补上，否则客户端会一直等
            if (!$this->headers_sent) {
                $this->sendHeaders();
            }
            $this->connection->close("0\r\n\r\n", true);
        } catch (\Throwable $e) {
            // 连接已经没了，忽略
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    private function sendHeaders(): bool
    {
        $reason = self::REASONS[$this->status] ?? 'OK';
        $lines = ['HTTP/1.1 ' . $this->status . ' ' . $reason];
        foreach ($this->headers as $name => $values) {
            // 流式响应由我们自己决定分帧方式，不能带 Content-Length / Transfer-Encoding
            if (strcasecmp($name, 'Content-Length') === 0 || strcasecmp($name, 'Transfer-Encoding') === 0) {
                continue;
            }
            foreach ((array) $values as $value) {
                $lines[] = $name . ': ' . $value;
            }
        }
        $lines[] = 'Transfer-Encoding: chunked';
        try {
            $ok = $this->connection->send(implode("\r\n", $lines) . "\r\n\r\n", true);
        } catch (\Throwable $e) {
            $ok = false;
        }
        if ($ok === false) {
            $this->closed = true;
            return false;
        }
        $this->headers_sent = true;

        return true;
    }
}
