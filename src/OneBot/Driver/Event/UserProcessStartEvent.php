<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

class UserProcessStartEvent extends DriverEvent
{
    public function __construct()
    {
        parent::__construct(Event::EVENT_USER_PROCESS_START);
    }
}
