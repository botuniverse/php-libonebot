<?php

declare(strict_types=1);

namespace OneBot\Driver\Coroutine;

use Co\System;
use OneBot\Driver\Driver;
use OneBot\Driver\Process\ExecutionResult;
use OneBot\Driver\Workerman\WorkermanDriver;
use RuntimeException;
use Swoole\Coroutine;

/**
 * 自适应执行耗时较长的常用操作
 */
class Adaptive
{
    /**
     * @var null|CoroutineInterface
     */
    private static $coroutine;

    /**
     * 通过不同的驱动初始化协程接口
     *
     * @param Driver $driver 驱动
     */
    public static function initWithDriver(Driver $driver)
    {
        if ($driver->getName() === 'swoole') {
            self::$coroutine = SwooleCoroutine::getInstance();
        } elseif ($driver->getName() === 'workerman' && PHP_VERSION_ID >= 80100) {
            // 只有 PHP >= 8.1 才能使用 Fiber 协程接口
            self::$coroutine = FiberCoroutine::getInstance();
        }
    }

    /**
     * 挂起多少秒
     *
     * @param float|int $time 暂停的秒数，支持小数到 0.001
     */
    public static function sleep($time)
    {
        $cid = self::$coroutine instanceof CoroutineInterface ? self::$coroutine->getCid() : -1;
        if ($cid === -1) {
            goto default_sleep;
        }
        if (self::$coroutine instanceof SwooleCoroutine) {
            Coroutine::sleep($time);
            return;
        }
        if (self::$coroutine instanceof FiberCoroutine) {
            WorkermanDriver::getInstance()->getEventLoop()->addTimer($time * 1000, function () use ($cid) {
                self::$coroutine->resume($cid);
            });
            self::$coroutine->suspend();
            return;
        }
        default_sleep:
        usleep($time * 1000 * 1000);
    }

    /**
     * 执行命令行
     *
     * @param string $cmd 命令行
     */
    public static function exec(string $cmd): ExecutionResult
    {
        $cid = self::$coroutine instanceof CoroutineInterface ? self::$coroutine->getCid() : -1;
        if ($cid === -1) {
            goto default_exec;
        }
        if (self::$coroutine instanceof SwooleCoroutine) {
            $result = System::exec($cmd);
            return new ExecutionResult($result['code'], $result['output']);
        }
        if (self::$coroutine instanceof FiberCoroutine) {
            $descriptorspec = [
                0 => ['pipe', 'r'],  // 标准输入，子进程从此管道中读取数据
                1 => ['pipe', 'w'],  // 标准输出，子进程向此管道中写入数据
                2 => STDERR, // 标准错误
            ];
            $res = proc_open('echo 456 && sleep 10 && echo 123', $descriptorspec, $pipes, getcwd());
            if (is_resource($res)) {
                $cid = self::$coroutine->getCid();
                WorkermanDriver::getInstance()->getEventLoop()->addReadEvent($pipes[1], function ($x) use ($cid, $res, $pipes) {
                    $stdout = stream_get_contents($x);
                    $status = proc_get_status($res);
                    if ($status['exitcode'] !== -1) {
                        WorkermanDriver::getInstance()->getEventLoop()->delReadEvent($x);
                        fclose($x);
                        fclose($pipes[0]);
                        $out = new ExecutionResult($status['exitcode'], $stdout);
                    } else {
                        $out = new ExecutionResult(-1);
                    }
                    self::$coroutine->resume($cid, $out);
                });
                return self::$coroutine->suspend();
            }
            throw new RuntimeException('Cannot open process with command ' . $cmd);
        }
        default_exec:
        exec($cmd, $output, $code);
        return new ExecutionResult($code, $output);
    }

    public static function getCoroutine(): ?CoroutineInterface
    {
        return self::$coroutine;
    }
}
