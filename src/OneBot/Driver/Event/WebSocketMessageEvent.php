<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

class WebSocketMessageEvent extends DriverEvent
{
    /**
     * @var string
     */
    private $data;

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

    public function __construct(int $fd, string $data, callable $send_callback)
    {
        parent::__construct(Event::EVENT_WEBSOCKET_MESSAGE);
        $this->fd = $fd;
        $this->data = $data;
        $this->send_callback = $send_callback;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getFd(): int
    {
        return $this->fd;
    }

    public function send($data)
    {
        return call_user_func($this->send_callback, $this->fd, $data);
    }

    public function setOriginFrame($frame)
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
