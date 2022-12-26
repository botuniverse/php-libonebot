<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

use Choir\WebSocket\FrameInterface;
use OneBot\Driver\Interfaces\SocketInterface;
use OneBot\Driver\Interfaces\WebSocketInterface;

abstract class WSServerSocketBase implements SocketInterface, WebSocketInterface
{
    use SocketFlag;
    use SocketConfig;

    abstract public function sendAll(FrameInterface $data): array;

    abstract public function close($id): bool;
}
