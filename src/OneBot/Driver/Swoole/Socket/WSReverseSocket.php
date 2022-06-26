<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole\Socket;

use OneBot\Driver\Socket\WSReverseSocketBase;
use OneBot\Http\WebSocket\FrameInterface;

class WSReverseSocket extends WSReverseSocketBase
{
    public function send(FrameInterface $data, $id = null): bool
    {
        $this->client->send($data->getData());
        return false;
    }
}
