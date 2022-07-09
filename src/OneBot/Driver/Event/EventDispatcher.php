<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use OneBot\Driver\ExceptionHandler;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

// TODO: 尝试把 EventDispatcher 全局唯一，以避免频繁的 new EventDispatcher
class EventDispatcher implements EventDispatcherInterface
{
    /**
     * 分发事件
     */
    public function dispatch(object $event): object
    {
        foreach (EventProvider::getEventListeners($event->getName()) as $listener) {
            try {
                // TODO: 允许 Listener 修改 $event
                // TODO: 在调用 listener 前先判断 isPropagationStopped
                $listener[1]($event);
            } catch (StopException $exception) {
                ob_logger()->debug('Event ' . $event . ' stopped');
                if ($event instanceof DriverEvent) {
                    $event->setPropagationStopped();
                }
                break;
            }
        }
        return $event;
    }

    /**
     * 一键分发事件，并handle错误
     */
    public static function dispatchWithHandler(object $event)
    {
        try {
            (new self())->dispatch($event);
        } catch (Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
        }
    }
}
