<?php

declare(strict_types=1);

namespace OneBot\ObjectPool;

use OneBot\Driver\SwooleDriver;
use OneBot\Driver\WorkermanDriver;
use SplQueue;
use Swoole\Coroutine\Channel;

/**
 * 抽象对象池
 */
abstract class AbstractObjectPool
{
    /** @var Channel|SplQueue 队列 */
    private $queue;

    /** @var array 活跃对象 */
    private $actives;

    public function __construct()
    {
        // TODO: 添加更多可配置项
        if (ob_driver_is(SwooleDriver::class)) {
            $this->queue = new Channel(swoole_cpu_num());
        } elseif (ob_driver_is(WorkermanDriver::class)) {
            $this->queue = new SplQueue();
        }
    }

    /**
     * 取出对象
     */
    public function take(): object
    {
        if ($this->getFreeCount() > 0) {
            // 如有可用对象则取用
            try {
                $object = $this->queue->pop();
            } catch (\RuntimeException $e) {
                // 此处用以捕获 SplQueue 在对象池空时抛出的异常
                throw new \RuntimeException('对象池已空，无法取出');
            }
            if (!$object) {
                // Swoole Channel 在通道关闭时会返回 false
                throw new \RuntimeException('对象池通道被关闭，无法去除');
            }
        } else {
            // 没有就整个新的
            $object = $this->makeObject();
        }
        $hash = spl_object_hash($object);
        // 为方便在归还时删除，使用数组key存储
        $this->actives[$hash] = '';

        return $object;
    }

    /**
     * 归还对象
     */
    public function return(object $object): bool
    {
        $hash = spl_object_hash($object);
        unset($this->actives[$hash]);

        // 放回队列里
        return $this->queue->push($object);
    }

    abstract protected function makeObject(): object;

    /**
     * 获取可用的对象数量
     */
    protected function getFreeCount(): int
    {
        $count = 0;
        if (ob_driver_is(SwooleDriver::class)) {
            $count = $this->queue->stats()['queue_num'];
        } elseif (ob_driver_is(WorkermanDriver::class)) {
            $count = $this->queue->count();
        }
        return max($count, 0);
    }

    /**
     * 获取活跃（已被取用）的对象数量
     */
    protected function getActiveCount(): int
    {
        return count($this->actives);
    }

    /**
     * 获取所有的对象数量
     */
    protected function getTotalCount(): int
    {
        return $this->getFreeCount() + $this->getActiveCount();
    }
}
