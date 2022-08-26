<?php

declare(strict_types=1);

namespace OneBot\Driver;

use OneBot\Util\Singleton;

abstract class DriverEventLoopBase
{
    use Singleton;

    /**
     * 驱动必须提供一个可以添加到对应驱动 EventLoop 的读接口
     *
     * @param resource $fd       文件描述符或资源 int
     * @param callable $callable 回调函数
     */
    abstract public function addReadEvent($fd, callable $callable);

    /**
     * 驱动必须提供一个可以删除对应驱动 EventLoop 的读接口
     *
     * @param resource $fd 文件描述符或资源 int
     */
    abstract public function delReadEvent($fd);

    /**
     * 驱动必须提供一个可以添加到对应驱动 EventLoop 的写接口
     * @param resource $fd       文件描述符或资源 int
     * @param callable $callable 回调函数
     */
    abstract public function addWriteEvent($fd, callable $callable);

    /**
     * 驱动必须提供一个可以删除对应驱动 EventLoop 的写接口
     *
     * @param resource $fd 文件描述符或资源 int
     */
    abstract public function delWriteEvent($fd);

    /**
     * 添加一个定时器
     *
     * @param int      $ms        间隔时间（单位为毫秒）
     * @param callable $callable  回调函数
     * @param int      $times     运行次数（默认只运行一次，如果为0或-1，则将会永久运行）
     * @param array    $arguments 回调要调用的参数
     */
    abstract public function addTimer(int $ms, callable $callable, int $times = 1, array $arguments = []): int;

    /**
     * 删除 Driver 的计时器
     *
     * @param int $timer_id 通过 addTimer() 方法返回的计时器 ID
     */
    abstract public function clearTimer(int $timer_id);
}
