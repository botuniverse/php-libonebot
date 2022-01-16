<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

use Psr\EventDispatcher\StoppableEventInterface;

class DriverEvent implements Event, StoppableEventInterface
{
    /**
     * @var bool
     */
    protected $propagationStopped = false;

    /**
     * @var string
     */
    protected $type = Event::EVENT_UNKNOWN;

    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * {@inheritDoc}
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * @throws StopException
     */
    public function stopPropagation(): void
    {
        throw new StopException($this);
    }

    /**
     * @internal
     */
    public function setPropagationStopped()
    {
        $this->propagationStopped = true;
    }
}
