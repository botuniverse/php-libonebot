<?php

declare(strict_types=1);

namespace OneBot\Driver\Coroutine;

use OneBot\Util\Singleton;
use Swoole\Coroutine;

class SwooleCoroutine implements CoroutineInterface
{
    use Singleton;

    private static $resume_values = [];

    public function create(callable $callback, ...$args): int
    {
        return go($callback, ...$args);
    }

    public function suspend()
    {
        Coroutine::yield();
        if (isset(self::$resume_values[$this->getCid()])) {
            $value = self::$resume_values[$this->getCid()];
            unset(self::$resume_values[$this->getCid()]);
            return $value;
        }
        return null;
    }

    public function resume(int $cid, $value = null)
    {
        if (Coroutine::exists($cid)) {
            self::$resume_values[$cid] = $value;
            Coroutine::resume($cid);
            return $cid;
        }
        ob_logger()->error('Swoole coroutine #' . $cid . ' not exists');
        return false;
    }

    public function getCid(): int
    {
        return Coroutine::getCid();
    }
}
