<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use OneBot\Driver\Coroutine\Adaptive;
use OneBot\Driver\Interfaces\HandledDispatcherInterface;
use OneBot\Exception\ExceptionHandler;

// TODO: 尝试把 EventDispatcher 全局唯一，以避免频繁的 new EventDispatcher
class EventDispatcher implements HandledDispatcherInterface
{
    /**
     * 分发事件
     */
    public function dispatch(object $event, bool $inside = false): object
    {
        if (($co = Adaptive::getCoroutine()) !== null && !$inside) {
            $co->create([$this, 'dispatch'], $event, true);
            return $event;
        }
        ob_logger()->warning('Dispatching event in fiber: ' . $co->getCid());
        foreach (ob_event_provider()->getEventListeners($event->getName()) as $listener) {
            try {
                // TODO: 允许 Listener 修改 $event
                // TODO: 在调用 listener 前先判断 isPropagationStopped
                $listener[1]($event);
            } catch (StopException $exception) {
                ob_logger()->debug('EventLoop ' . $event . ' stopped');
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
    public function dispatchWithHandler(object $event)
    {
        try {
            (new self())->dispatch($event);
        } catch (\Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
        }
    }
}
