<?php

declare(strict_types=1);

namespace OneBot\Driver\Interfaces;

use Psr\EventDispatcher\EventDispatcherInterface;

interface HandledDispatcherInterface extends EventDispatcherInterface
{
    public function dispatchWithHandler(object $event);
}
