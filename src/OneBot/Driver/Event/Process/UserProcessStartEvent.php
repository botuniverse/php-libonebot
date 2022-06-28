<?php

declare(strict_types=1);

namespace OneBot\Driver\Event\Process;

use OneBot\Driver\Event\DriverEvent;
use OneBot\Driver\Interfaces\ProcessInterface;

class UserProcessStartEvent extends DriverEvent
{
    /**
     * @var ProcessInterface
     */
    protected $process;

    public function __construct(ProcessInterface $process)
    {
        $this->process = $process;
    }

    public function getProcess(): ProcessInterface
    {
        return $this->process;
    }
}
