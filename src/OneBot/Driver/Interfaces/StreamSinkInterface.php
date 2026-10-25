<?php

declare(strict_types=1);

namespace OneBot\Driver\Interfaces;

/**
 * 流式响应的「出水口」抽象
 *
 * 驱动层（Swoole / Workerman）各自实现，业务代码只面向本接口写数据，
 * 因此同一份 SSE 输出逻辑可以跨驱动复用。
 *
 * 约定：
 * - write() 返回 false 表示对端已断开（浏览器关页面、用户按 Ctrl+C），
 *   生产者应当立刻停止拉取上游数据并退出，否则会白白占用上游连接。
 * - end() 只能调用一次，且必须在 write() 之后调用，用于结束 chunked 响应。
 */
interface StreamSinkInterface
{
    /**
     * 写出一块数据（立刻 flush 到客户端，不做缓冲）
     *
     * @param string $chunk 原始字节，SSE 场景通常是一帧或多帧 "data: ...\n\n"
     *
     * @return bool 对端是否仍然可写；false 表示连接已关闭，应停止发送
     */
    public function write(string $chunk): bool;

    /**
     * 结束响应（会真正关掉这条 chunked 流）
     */
    public function end(): void;

    /**
     * 连接是否已经关闭（写入失败或已 end 都会置为 true）
     */
    public function isClosed(): bool;
}
