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

    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
    }
}
