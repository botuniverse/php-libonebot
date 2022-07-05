<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole\Socket;

use OneBot\Driver\Socket\HttpServerSocketBase;
use Swoole\Server;

class HttpServerSocket extends HttpServerSocketBase
{
    /** @var Server|Server\Port|\Swoole\Http\Server|\Swoole\WebSocket\Server */
    protected $socket_obj;

    /** @var array */
    protected $config;

    public function __construct($server_or_port, array $config = [])
    {
        $this->socket_obj = $server_or_port;
        $this->config = $config;
    }

    public function getPort(): int
    {
        return $this->socket_obj->port;
    }
}
