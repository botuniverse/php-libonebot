<?php

declare(strict_types=1);

namespace OneBot\Driver\Interfaces;

use Choir\WebSocket\FrameInterface;

interface WebSocketInterface
{
    /**
     * WebSocket 发送一条数据帧，可传入 Frame 对象，也可直接传入字符串
     *
     * @param null|int|string       $fd   连接ID
     * @param FrameInterface|string $data 数据
     */
    public function send($data, $fd): bool;
}
