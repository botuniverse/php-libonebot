<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use Psr\EventDispatcher\ListenerProviderInterface;

class EventProvider implements ListenerProviderInterface
{
    private static $_events = [];

    public static function addEventListener(string $name, $callback)
    {
        self::$_events[$name][] = $callback;
    }

    public static function getEventListeners(string $event_name): array
    {
        return self::$_events[$event_name] ?? [];
    }

    public function getListenersForEvent(object $event): iterable
    {
        return self::$_events[$event->getType()] ?? [];
    }
}
