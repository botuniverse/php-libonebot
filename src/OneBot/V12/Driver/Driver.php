<?php

declare(strict_types=1);

namespace OneBot\V12\Driver;

use OneBot\V12\Config\ConfigInterface;
use OneBot\V12\Object\EventObject;

abstract class Driver
{
    /** @var ConfigInterface */
    protected $config;

    public function getName(): string
    {
        return rtrim(strtolower(self::class), 'driver');
    }

    public function setConfig(ConfigInterface $config)
    {
        $this->config = $config;
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    abstract public function emitOBEvent(EventObject $event);

    abstract public function initComm();

    abstract public function run();
}
