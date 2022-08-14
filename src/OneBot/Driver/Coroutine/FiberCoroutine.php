<?php

declare(strict_types=1);

namespace OneBot\Driver\Coroutine;

use Exception;
use OneBot\Util\Singleton;
use RuntimeException;
use SplStack;

class FiberCoroutine implements CoroutineInterface
{
    use Singleton;

    /** @var SplStack */
    private static $fiber_stacks;

    /** @var array<int, \Fiber> */
    private static $suspended_fiber_map = [];

    public function create(callable $callback, ...$args): int
    {
        if (PHP_VERSION_ID < 80100) {
            throw new Exception('You need PHP >= 8.1 to enable Fiber feature!');
        }
        $fiber = new \Fiber($callback);

        if (self::$fiber_stacks === null) {
            self::$fiber_stacks = new SplStack();
        }

        self::$fiber_stacks->push($fiber);
        $fiber->start(...$args);
        self::$fiber_stacks->pop();
        $id = spl_object_id($fiber);
        if (!$fiber->isTerminated()) {
            self::$suspended_fiber_map[$id] = $fiber;
        }
        return $id;
    }

    public function suspend()
    {
        if (PHP_VERSION_ID < 80100) {
            throw new Exception('You need PHP >= 8.1 to enable Fiber feature!');
        }
        return \Fiber::suspend();
    }

    public function resume(int $cid, $value = null)
    {
        if (PHP_VERSION_ID < 80100) {
            throw new Exception('You need PHP >= 8.1 to enable Fiber feature!');
        }
        if (!isset(self::$suspended_fiber_map[$cid])) {
            ob_logger()->error('ID ' . $cid . ' Fiber not suspended!');
            return;
        }
        self::$fiber_stacks->push(self::$suspended_fiber_map[$cid]);
        self::$suspended_fiber_map[$cid]->resume($value);
        self::$fiber_stacks->pop();
        if (self::$suspended_fiber_map[$cid]->isTerminated()) {
            unset(self::$suspended_fiber_map[$cid]);
        }
    }

    public function getCid(): int
    {
        try {
            $v = self::$fiber_stacks->pop();
            self::$fiber_stacks->push($v);
        } catch (RuntimeException $e) {
            return -1;
        }
        return spl_object_id($v);
    }
}
