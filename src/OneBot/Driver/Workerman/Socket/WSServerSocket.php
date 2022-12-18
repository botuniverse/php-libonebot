<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman\Socket;

use Choir\WebSocket\FrameInterface;
use OneBot\Driver\Socket\WSServerSocketBase;
use OneBot\Driver\Workerman\Worker;
use Workerman\Connection\TcpConnection;

class WSServerSocket extends WSServerSocketBase
{
    /**
     * @var Worker
     */
    public $worker;

    /**
     * @var TcpConnection[]
     */
    public $connections = [];

    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
    }

    public function send(FrameInterface $data, $id = null): bool
    {
        if (!isset($this->connections[$id])) {
            ob_logger()->warning('链接不存在，可能已被关闭或未连接');
            return false;
        }
        return $this->connections[$id]->send($data->getData());
    }

    public function sendAll(FrameInterface $data): array
    {
        $result = [];
        foreach ($this->connections as $id => $connection) {
            $result[$id] = $connection->send($data->getData());
        }
        return $result;
    }

    public function close($id): bool
    {
        if (!isset($this->connections[$id])) {
            ob_logger()->warning('链接不存在，可能已被关闭或未连接');
            return false;
        }
        $this->connections[$id]->close();
        unset($this->connections[$id]);
        return true;
    }

    public function getWorker(): Worker
    {
        return $this->worker;
    }
}
