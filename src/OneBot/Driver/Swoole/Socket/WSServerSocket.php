<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole\Socket;

use Choir\WebSocket\FrameInterface;
use OneBot\Driver\Socket\WSServerSocketBase;
use Swoole\Server\Port;
use Swoole\WebSocket\Server;

class WSServerSocket extends WSServerSocketBase
{
    protected ?Server $server;

    protected ?Port $port;

    public function __construct(?Server $server = null, ?Port $port = null, array $config = [])
    {
        $this->server = $server;
        $this->port = $port;
        $this->config = $config;
    }

    public function close($fd): bool
    {
        return false;
    }

    public function send($data, $fd): bool
    {
        if ($data instanceof FrameInterface) {
            return $this->server->push($fd, $data->getData(), $data->getOpcode());
        }
        return $this->server->push($fd, $data);
    }

    public function sendMultiple($data, ?callable $filter = null): array
    {
        $result = [];
        if ($this->port !== null) {
            $a = $this->port->connections;
        } else {
            $a = $this->server->connections;
        }
        foreach ($a as $fd) {
            if ($this->server->exists($fd) && ($filter === null || $filter($fd, $this))) {
                $result[$fd] = $this->send($data, $fd);
            }
        }
        return $result;
    }
}
