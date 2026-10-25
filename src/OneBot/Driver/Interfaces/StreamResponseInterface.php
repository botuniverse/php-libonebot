<?php

declare(strict_types=1);

namespace OneBot\Driver\Interfaces;

use Psr\Http\Message\ResponseInterface;

/**
 * 可分块写出的响应（SSE / 打字机 / 大文件转发）
 *
 * 普通响应走 PSR-7 的 body，驱动会等 body 全部就绪后一次性 end()；
 * 实现了本接口的响应则交给驱动「边算边发」，body 不参与输出。
 *
 * 典型实现见 OneBot\Driver\Stream\SseResponse。
 *
 * 注意：
 * - writeTo() 在请求所在的协程/回调里执行，可以在里面做阻塞 IO（读上游 SSE、sleep、轮询），
 *   Swoole 下这些 IO 会被协程调度，不会阻塞其它请求；
 * - 一旦开始写数据，HTTP 状态码和响应头就已经发出去了，此时再抛异常无法改成 500，
 *   驱动层会尽力写一条错误事件再结束流。
 */
interface StreamResponseInterface extends ResponseInterface
{
    /**
     * 把响应体逐块写出去
     *
     * @param StreamSinkInterface $sink 写出目标；write() 返回 false 时应立即停止生产
     */
    public function writeTo(StreamSinkInterface $sink): void;
}
