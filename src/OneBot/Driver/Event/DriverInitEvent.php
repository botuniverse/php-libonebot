<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use OneBot\Driver\Driver;

class DriverInitEvent extends DriverEvent
{
    /** @var Driver */
    private $driver;

    private $driver_mode;

    public function __construct(Driver $driver, $driver_mode = Driver::MULTI_PROCESS)
    {
        $this->driver = $driver;
        $this->driver_mode = $driver_mode;
    }

    public function getDriver(): Driver
    {
        return $this->driver;
    }

    /**
     * @return int|mixed
     */
    public function getDriverMode()
    {
        return $this->driver_mode;
    }
}
