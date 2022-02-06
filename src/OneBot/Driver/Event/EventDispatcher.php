<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

class EventDispatcher implements EventDispatcherInterface
{
    /**
     * 分发事件
     */
    public function dispatch(object $event): object
    {
        foreach (EventProvider::getEventListeners($event->getType()) as $listener) {
            try {
                // TODO: 允许 Listener 修改 $event
                $listener($event);
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
}
