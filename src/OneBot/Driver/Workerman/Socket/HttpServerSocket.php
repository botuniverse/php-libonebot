<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman\Socket;

use OneBot\Driver\Socket\HttpServerSocketBase;
use OneBot\Driver\Workerman\Worker;

class HttpServerSocket extends HttpServerSocketBase
{
    /**
     * @var Worker
     */
    public $worker;

    public function __construct(Worker $worker, array $config)
    {
        $this->worker = $worker;
        $this->config = $config;
    }

    public function getPort(): int
    {
        return $this->config['port'];
    }

    public function getWorker(): Worker
    {
        return $this->worker;
    }
}
