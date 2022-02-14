<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use OneBot\Driver\Event\Process\ManagerStartEvent;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class DriverEventTest extends TestCase
{
    public function testCustomName()
    {
        $this->assertEquals(ManagerStartEvent::class, ManagerStartEvent::getName());
    }
}
