<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

class WorkerStopEvent extends DriverEvent
{
    public function __construct()
    {
        parent::__construct(Event::EVENT_WORKER_STOP);
    }
}
