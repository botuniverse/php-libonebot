<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman\Socket;

use Choir\WebSocket\FrameInterface;
use OneBot\Driver\Socket\WSServerSocketBase;
use OneBot\Driver\Workerman\Worker;
use Workerman\Connection\TcpConnection;

class WSServerSocket extends WSServerSocketBase
{
    public Worker $worker;

    /**
     * @var TcpConnection[]
     */
    public array $connections = [];

    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
    }

    public function send($data, $fd): bool
    {
        if (!isset($this->connections[$fd])) {
            ob_logger()->warning('链接不存在，可能已被关闭或未连接');
            return false;
        }
        if ($data instanceof FrameInterface) {
            $data = $data->getData();
        }
        return $this->connections[$fd]->send($data);
    }

    public function sendMultiple($data, ?callable $filter = null): array
    {
        $result = [];
        if ($data instanceof FrameInterface) {
            $data = $data->getData();
        }
        foreach ($this->connections as $fd => $connection) {
            if ($connection->getStatus() === TcpConnection::STATUS_ESTABLISHED && ($filter === null || $filter($fd, $this))) {
                $result[$fd] = $connection->send($data);
            }
        }
        return $result;
    }

    public function sendAll($data): array
    {
        $result = [];
        if ($data instanceof FrameInterface) {
            $data = $data->getData();
        }
        foreach ($this->connections as $id => $connection) {
            $result[$id] = $connection->send($data);
        }
        return $result;
    }

    public function close($fd): bool
    {
        if (!isset($this->connections[$fd])) {
            ob_logger()->warning('链接不存在，可能已被关闭或未连接');
            return false;
        }
        $this->connections[$fd]->close();
        unset($this->connections[$fd]);
        return true;
    }

    public function getWorker(): Worker
    {
        return $this->worker;
    }
}
