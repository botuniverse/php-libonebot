<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class EventProviderTest extends TestCase
{
    public function testSortEvents()
    {
        EventProvider::addEventListener('a', 'test', 1);
        EventProvider::addEventListener('a', 'test2', 5);
        EventProvider::addEventListener('a', 'test3', 3);
        EventProvider::addEventListener('a', 'test4', 2);
        $list = EventProvider::getEventListeners('a');
        $map = [5, 3, 2, 1];
        $map2 = ['test2', 'test3', 'test4', 'test'];
        foreach ($list as $k => $v) {
            $this->assertEquals($map[$k], $v[0]);
            $this->assertEquals($map2[$k], $v[1]);
        }
    }
}
