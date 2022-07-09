<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

use OneBot\Driver\Interfaces\SocketInterface;

abstract class HttpServerSocketBase implements SocketInterface
{
    use SocketFlag;

    abstract public function getPort(): int;
}
