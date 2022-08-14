<?php

declare(strict_types=1);

namespace OneBot\Driver\Coroutine;

interface CoroutineInterface
{
    public static function getInstance(...$arg);

    /**
     * 创建一个协程并运行
     *
     * 在执行协程过程中，如果遇到 suspend，则立刻返回协程 ID
     *
     * @param callable $callback 回调
     * @param mixed    ...$args  传参
     */
    public function create(callable $callback, ...$args): int;

    /**
     * 挂起当前协程
     *
     * @return mixed
     */
    public function suspend();

    /**
     * 根据提供的协程 ID 恢复一个协程继续运行
     *
     * @param  int   $cid   协程 ID
     * @param  mixed $value 要传给 suspend 返回值的内容
     * @return mixed
     */
    public function resume(int $cid, $value = null);

    /**
     * 获取当前协程 ID
     */
    public function getCid(): int;
}
