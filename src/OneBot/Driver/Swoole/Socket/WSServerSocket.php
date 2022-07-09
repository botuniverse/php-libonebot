<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole\Socket;

use OneBot\Driver\Socket\WSServerSocketBase;
use OneBot\Http\WebSocket\FrameInterface;
use Swoole\Server;

class WSServerSocket extends WSServerSocketBase
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

    public function sendAll(FrameInterface $data): array
    {
        return [];
    }

    public function close($id): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function send(FrameInterface $data, $id = null): bool
    {
        return false;
    }
}
