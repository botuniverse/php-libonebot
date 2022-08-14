<?php

declare(strict_types=1);

namespace OneBot\Driver\Coroutine;

use OneBot\Util\Singleton;

class FalseCoroutine implements CoroutineInterface
{
    use Singleton;

    /**
     * {@inheritDoc}
     */
    public function create(callable $callback, ...$args): int
    {
        $callback(...$args);
        return -1;
    }

    /**
     * {@inheritDoc}
     */
    public function suspend()
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function resume(int $cid, $value = null)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getCid(): int
    {
        return -1;
    }
}
