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

    /**
     * @var int
     */
    protected $port;

    public function __construct(Worker $worker, int $port)
    {
        $this->worker = $worker;
        $this->port = $port;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
