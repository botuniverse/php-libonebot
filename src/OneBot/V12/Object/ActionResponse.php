<?php

declare(strict_types=1);

namespace OneBot\V12\Object;

use OneBot\V12\RetCode;
use ReturnTypeWillChange;

/**
 * @property mixed $echo
 */
class ActionResponse implements \JsonSerializable, \IteratorAggregate
{
    public string $status = 'ok';

    public int $retcode = 0;

    public array $data = [];

    public string $message = '';

    /**
     * @var mixed
     */
    public $echo = null;

    public static function create($echo = null): ActionResponse
    {
        $a = new self();
        if (($echo instanceof Action) && $echo->echo !== null) {
            $a->echo = $echo->echo;
        } elseif (is_string($echo)) {
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
    #[\ReturnTypeWillChange]
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->jsonSerialize());
    }

    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this as $k => $v) {
            if ($k === 'echo' && $v === null) {
                continue;
            }
            $data[$k] = $v;
        }
        return $data;
    }
}
