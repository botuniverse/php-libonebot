<?php

declare(strict_types=1);

namespace OneBot\Driver\Interfaces;

use Psr\EventDispatcher\ListenerProviderInterface;

interface SortedProviderInterface extends ListenerProviderInterface
{
    /**
     * 获取事件监听器
     *
     * @param  string          $event_name 事件名称
     * @return array<callable>
     */
    public function getEventListeners(string $event_name): array;

    /**
     * 添加事件监听器
     *
     * @param string   $name     事件名称
     * @param callable $callback 事件回调
     * @param int      $level    事件等级
     */
    public function addEventListener(string $name, callable $callback, int $level = 20);
}
