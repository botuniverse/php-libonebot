<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole;

use OneBot\Driver\Interfaces\StreamSinkInterface;
use Swoole\Http\Response;

/**
 * Swoole 驱动的流式出水口
 *
 * Swoole 的 Http\Response::write() 会在第一次写入时自动切到
 * Transfer-Encoding: chunked 并立刻把数据推到 socket，所以这里不需要手动 flush。
 */
class SwooleStreamSink implements StreamSinkInterface
{
    private Response $response;

    private bool $closed = false;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function write(string $chunk): bool
    {
        if ($this->closed) {
            return false;
        }
        // Swoole 的 write('') 会把空 chunk 发出去，直接跳过
        if ($chunk === '') {
            return true;
        }
        try {
            $ok = $this->response->write($chunk);
        } catch (\Throwable $e) {
            $ok = false;
        }
        if ($ok === false) {
            // 对端断开（浏览器关页面 / 网络断）时 Swoole 返回 false
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
            $this->response->end();
        } catch (\Throwable $e) {
            // 连接已经没了，忽略
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }
}
