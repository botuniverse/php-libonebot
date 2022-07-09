<?php

declare(strict_types=1);

namespace OneBot\Http\WebSocket;

/**
 * Interface ConnectionInterface
 */
interface ConnectionInterface
{
    /**
     * @return mixed
     */
    public function send(FrameInterface $frame);

    /**
     * RFC6455
     *
     * @return mixed
     */
    public function close(int $close_code = 1000);
}
