<?php

declare(strict_types=1);

namespace OneBot\V12\Driver;

use OneBot\V12\Driver\Config\Config;
use OneBot\V12\Object\EventObject;

interface Driver
{
    public function getName(): string;

    public function setConfig(Config $config);

    public function getConfig(): Config;

    public function emitOBEvent(EventObject $event);

    public function initComm();

    public function run();
}
