<?php

declare(strict_types=1);

namespace OneBot\Driver\Coroutine;


use OneBot\Driver\Process\ExecutionResult;

interface CoroutineInterface
{
    /**
     * 返回当前协程实现是否可以使用
     */
    public static function isAvailable(): bool;

    /**
     * 创建一个协程并运行
     *
     * 在执行协程过程中，如果遇到 suspend，则立刻返回协程 ID
     *
     * @param callable $callback 回调
     * @param mixed    ...$args  传参
     */
    public function create(callable $callback, ...$args): int;

    public function exists(int $cid): bool;

    /**
     * 挂起当前协程
     *
     * @return mixed
     */
    public function suspend();

    /**
     * 根据提供的协程 ID 恢复一个协程继续运行
     *
     * @param  int       $cid   协程 ID
     * @param  mixed     $value 要传给 suspend 返回值的内容
     * @return false|int
     */
    public function resume(int $cid, $value = null);

    /**
     * 获取当前协程 ID
     */
    public function getCid(): int;

    /**
     * @param float|int $time 协程 sleep
     */
    public function sleep($time);

    /**
     * 协程执行命令行
     * @param string $cmd 命令行
     */
    public function exec(string $cmd): ExecutionResult;

    /**
     * 获取正在运行的协程数量
     */
    public function getCount(): int;
}
