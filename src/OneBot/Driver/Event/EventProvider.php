<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use OneBot\Driver\Interfaces\SortedProviderInterface;
use OneBot\Util\Singleton;

class EventProvider implements SortedProviderInterface
{
    use Singleton;

    /**
     * @var array<string, array<array<int, callable>>> 已注册的事件监听器
     */
    private static $_events = [];

    /**
     * 添加事件监听器
     *
     * @param string   $name     事件名称
     * @param callable $callback 事件回调
     * @param int      $level    事件等级
     */
    public function addEventListener(string $name, callable $callback, int $level = 20)
    {
        /*
         * TODO: 尝试同时支持类名和自定义名称作为事件名
         * NOTE: 这有可能导致事件日志难以追溯？
         * NOTE: 使用自定义名称的一个替代方法是在 Event 类中实现 getName 方法
         * NOTE: 如果使用自定义名称，则需要在事件处理器中使用 `$event->getName()` 获取事件名
         * NOTE: 或者是否由其他可能的方法支持自定义名称，从而避免频繁的 new EventDispatcher
         */
        self::$_events[$name][] = [$level, $callback];
        $this->sortEvents($name);
    }

    /**
     * 获取事件监听器
     *
     * @param  string          $event_name 事件名称
     * @return array<callable>
     */
    public function getEventListeners(string $event_name): array
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
        return self::getEventListeners($event->getName());
    }

    private function sortEvents($name)
    {
        usort(self::$_events[$name], function ($a, $b) {
            return $a[0] <= $b[0] ? -1 : 1;
        });
    }
}
