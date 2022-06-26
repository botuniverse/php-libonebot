<?php

declare(strict_types=1);

namespace OneBot\Driver\Interfaces;

use OneBot\Http\WebSocket\FrameInterface;

interface WebSocketInterface
{
    /**
     * @param null|int|string $id   连接ID
     * @param FrameInterface  $data 数据
     */
    public function send(FrameInterface $data, $id = null): bool;
}
