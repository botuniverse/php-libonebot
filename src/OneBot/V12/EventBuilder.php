<?php

declare(strict_types=1);

namespace OneBot\V12;

use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\OneBotEvent;

class EventBuilder
{
    private array $data = [];

    private ?OneBotEvent $event = null;

    public function __construct(string $type, string $detail_type = '', string $sub_type = '', ?string $id = null, $time = null)
    {
        if (!in_array($type, ['message', 'meta', 'request', 'notice'])) {
            throw new \InvalidArgumentException('Invalid event type');
        }
        $this->data['type'] = $type;
        $this->data['id'] = $id ?? ob_uuidgen();
        $this->data['time'] = $time ?? time();
        $this->data['detail_type'] = $detail_type;
        $this->data['sub_type'] = $sub_type;
    }

    public function feed(string $key, $value): EventBuilder
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function valid(): bool
    {
        try {
            $this->event = new OneBotEvent($this->data);
            return true;
        }catch (OneBotException $e) {
            return false;
        }
    }

    /**
     * @throws OneBotException
     */
    public function build(): OneBotEvent
    {
        return $this->event ?? new OneBotEvent($this->data);
    }
}
