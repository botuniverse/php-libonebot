<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use Psr\EventDispatcher\ListenerProviderInterface;

class EventProvider implements ListenerProviderInterface
{
    /**
     * @var array<string, array<callable>> 已注册的事件监听器
     */
    private static $_events = [];

    /**
     * 添加事件监听器
     *
     * @param string   $name     事件名称
     * @param callable $callback 事件回调
     */
    public static function addEventListener(string $name, callable $callback): void
    {
        self::$_events[$name][] = $callback;
    }

    /**
     * 获取事件监听器
     *
     * @param  string          $event_name 事件名称
     * @return array<callable>
     */
    public static function getEventListeners(string $event_name): array
    {
        return self::$_events[$event_name] ?? [];
    }

    /**
     * 获取事件监听器
     *
     * @param  object             $event 事件对象
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        return self::getEventListeners($event->getType());
    }
}
