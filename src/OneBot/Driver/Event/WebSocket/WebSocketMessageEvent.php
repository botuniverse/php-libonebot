<?php

declare(strict_types=1);

namespace OneBot\Driver\Event\WebSocket;

use OneBot\Driver\Event\DriverEvent;
use OneBot\Http\WebSocket\FrameInterface;

class WebSocketMessageEvent extends DriverEvent
{
    /**
     * @var FrameInterface
     */
    private $frame;

    /**
     * @var int
     */
    private $fd;

    /**
     * @var mixed
     */
    private $origin_frame;

    /**
     * @var callable
     */
    private $send_callback;

    public function __construct(int $fd, FrameInterface $frame, callable $send_callback)
    {
        $this->fd = $fd;
        $this->frame = $frame;
        $this->send_callback = $send_callback;
    }

    public function getFrame(): FrameInterface
    {
        return $this->frame;
    }

    public function getFd(): int
    {
        return $this->fd;
    }

    public function send($data)
    {
        return call_user_func($this->send_callback, $this->fd, $data);
    }

    public function setOriginFrame($frame): void
    {
        $this->origin_frame = $frame;
    }

    /**
     * @return mixed
     */
    public function getOriginFrame()
    {
        return $this->origin_frame;
    }
}
