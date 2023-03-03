<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

use OneBot\Driver\Interfaces\SocketInterface;
use OneBot\Driver\Interfaces\WebSocketInterface;

abstract class WSServerSocketBase implements SocketInterface, WebSocketInterface
{
    use SocketFlag;
    use SocketConfig;

    abstract public function sendMultiple($data, ?callable $filter = null): array;

    abstract public function close($fd): bool;
}
