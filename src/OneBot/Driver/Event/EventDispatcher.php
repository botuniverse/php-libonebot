<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var string
     */
    private $event_name;

    /**
     * @throws Exception
     */
    public function __construct(string $event_name)
    {
        $this->event_name = $event_name;
        if (empty(EventProvider::getEventListeners($event_name))) {
            ob_logger()->debug("Event {$event_name} has no listeners");
        }
    }

    public function dispatch(object $event): object
    {
        foreach (EventProvider::getEventListeners($this->event_name) as $listener) {
            try {
                $listener($event);
            } catch (StopException $exception) {
                ob_logger()->debug('Event ' . $this->event_name . ' stopped');
                if ($event instanceof DriverEvent) {
                    $event->setPropagationStopped();
                }
                break;
            }
        }
        return $event;
    }
}
