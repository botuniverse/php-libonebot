<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman\Socket;

use OneBot\Driver\Socket\WSReverseSocketBase;
use OneBot\Http\WebSocket\FrameInterface;

class WSReverseSocket extends WSReverseSocketBase
{
    public function send(FrameInterface $data, $id = null): bool
    {
        // TODO: 编写发送 ws_reverse 下给服务端传输的数据
        return false;
    }
}
