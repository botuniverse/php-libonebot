<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use OneBot\Driver\Driver;

class DriverInitEvent extends DriverEvent
{
    /** @var Driver */
    private $driver;

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    public function getDriver(): Driver
    {
        return $this->driver;
    }
}
