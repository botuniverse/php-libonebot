<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

use OneBot\Driver\Interfaces\SocketInterface;
use OneBot\Driver\Interfaces\WebSocketInterface;
use OneBot\Http\WebSocket\FrameInterface;

abstract class WSServerSocketBase implements SocketInterface, WebSocketInterface
{
    use SocketFlag;

    abstract public function sendAll(FrameInterface $data): array;

    abstract public function close($id): bool;
}
