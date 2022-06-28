<?php

declare(strict_types=1);

namespace OneBot\V12\Action;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use OneBot\V12\RetCode;
use ReturnTypeWillChange;

/**
 * @property mixed $echo
 */
class ActionResponse implements JsonSerializable, IteratorAggregate
{
    public $status = 'ok';

    public $retcode = 0;

    public $data = [];

    public $message = '';

    public static function create($echo = null): ActionResponse
    {
        $a = new self();
        if ($echo !== null) {
            $a->echo = $echo;
        }
        return $a;
    }

    public function ok($data = []): ActionResponse
    {
        $this->status = 'ok';
        $this->retcode = 0;
        $this->data = $data;
        $this->message = '';
        return $this;
    }

    public function fail($retcode, $message = ''): ActionResponse
    {
        $this->status = 'failed';
        $this->retcode = $retcode;
        $this->data = [];
        $this->message = $message === '' ? RetCode::getMessage($retcode) : $message;
        return $this;
    }

    /**
     * @noinspection PhpLanguageLevelInspection
     */
    #[ReturnTypeWillChange]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this);
    }

    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this as $k => $v) {
            $data[$k] = $v;
        }
        return $data;
    }
}
