<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

class StopException extends \Exception
{
    private DriverEvent $event;

    public function __construct(DriverEvent $event, $message = '', $code = 0, \Throwable $previous = null)
    {
        $this->event = $event;
        parent::__construct($message, $code, $previous);
    }

    public function getEvent(): DriverEvent
    {
        return $this->event;
    }
}
