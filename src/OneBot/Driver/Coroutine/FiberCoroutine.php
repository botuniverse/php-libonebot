<?php

declare(strict_types=1);

namespace OneBot\Driver\Coroutine;

use OneBot\Driver\Driver;
use OneBot\Driver\Process\ExecutionResult;
use OneBot\Util\Singleton;

class FiberCoroutine implements CoroutineInterface
{
    use Singleton;

    private static ?\SplStack $fiber_stacks = null;

    /** @var array<int, \Fiber> */
    private static array $suspended_fiber_map = [];

    private Driver $driver;

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    public static function isAvailable(): bool
    {
        return PHP_VERSION_ID >= 80100;
    }

    /**
     * @throws \Throwable
     * @throws \RuntimeException
     */
    public function create(callable $callback, ...$args): int
    {
        if (PHP_VERSION_ID < 80100) {
            throw new \RuntimeException('You need PHP >= 8.1 to enable Fiber feature!');
        }
        $fiber = new \Fiber($callback);

        if (self::$fiber_stacks === null) {
            self::$fiber_stacks = new \SplStack();
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

    public function exists(int $cid): bool
    {
        return isset(self::$suspended_fiber_map[$cid]);
    }

    /**
     * @throws \Throwable
     * @throws \RuntimeException
     */
    public function suspend()
    {
        if (PHP_VERSION_ID < 80100) {
            throw new \RuntimeException('You need PHP >= 8.1 to enable Fiber feature!');
        }
        return \Fiber::suspend();
    }

    /**
     * @param  null|mixed        $value
     * @throws \RuntimeException
     * @throws \Throwable
     * @return false|int
     */
    public function resume(int $cid, $value = null)
    {
        if (PHP_VERSION_ID < 80100) {
            throw new \RuntimeException('You need PHP >= 8.1 to enable Fiber feature!');
        }
        if (!isset(self::$suspended_fiber_map[$cid])) {
            return false;
        }
        self::$fiber_stacks->push(self::$suspended_fiber_map[$cid]);
        self::$suspended_fiber_map[$cid]->resume($value);
        self::$fiber_stacks->pop();
        if (self::$suspended_fiber_map[$cid]->isTerminated()) {
            unset(self::$suspended_fiber_map[$cid]);
        }
        return $cid;
    }

    public function getCid(): int
    {
        try {
            $v = self::$fiber_stacks->pop();
            self::$fiber_stacks->push($v);
        } catch (\RuntimeException $e) {
            return -1;
        }
        return spl_object_id($v);
    }

    /**
     * @param  mixed             $time
     * @throws \Throwable
     * @throws \RuntimeException
     */
    public function sleep($time)
    {
        if (($cid = $this->getCid()) !== -1) {
            $this->driver->getEventLoop()->addTimer($time * 1000, function () use ($cid) {
                $this->resume($cid);
            });
            $this->suspend();
            return;
        }

        usleep($time * 1000 * 1000);
    }

    /**
     * @throws \Throwable
     * @throws \RuntimeException
     */
    public function exec(string $cmd): ExecutionResult
    {
        if (($cid = $this->getCid()) !== -1) {
            $descriptorspec = [
                0 => ['pipe', 'r'],  // 标准输入，子进程从此管道中读取数据
                1 => ['pipe', 'w'],  // 标准输出，子进程向此管道中写入数据
                2 => STDERR, // 标准错误
            ];
            $res = proc_open($cmd, $descriptorspec, $pipes, getcwd());
            if (is_resource($res)) {
                $this->driver->getEventLoop()->addReadEvent($pipes[1], function ($x) use ($cid, $res, $pipes) {
                    $stdout = stream_get_contents($x);
                    $status = proc_get_status($res);
                    $this->driver->getEventLoop()->delReadEvent($x);
                    if ($status['exitcode'] !== -1) {
                        fclose($x);
                        fclose($pipes[0]);
                        $out = new ExecutionResult($status['exitcode'], $stdout);
                    } else {
                        $out = new ExecutionResult(-1);
                    }
                    $this->resume($cid, $out);
                });
                return $this->suspend();
            }
            throw new \RuntimeException('Cannot open process with command ' . $cmd);
        }

        exec($cmd, $output, $code);
        return new ExecutionResult($code, $output);
    }

    public function getCount(): int
    {
        return self::$fiber_stacks->count();
    }
}
