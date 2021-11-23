<?php

declare(strict_types=1);

namespace OneBot\ObjectPool;

use Exception;
use Swoole\Coroutine\Channel;

/**
 * 抽象对象池
 * 只能在Swoole协程中使用
 */
abstract class AbstractObjectPool
{
    /** @var Channel 队列 */
    private $queue;

    /** @var array 活跃对象 */
    private $actives;

    public function __construct()
    {
        // TODO: 添加更多可配置项
        $this->queue = new Channel(swoole_cpu_num());
    }

    /**
     * 取出对象
     *
     * @throws Exception
     */
    public function take(): object
    {
        if ($this->getFreeCount() > 0) {
            // 如有可用对象则取用
            $object = $this->queue->pop(5);
            if (!$object) {
                throw new Exception('取出对象时等待超时');
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
        return $this->queue->push($object, 5);
    }

    abstract protected function makeObject(): object;

    /**
     * 获取可用的对象数量
     */
    protected function getFreeCount(): int
    {
        $count = $this->queue->stats()['queue_num'];
        return $count < 0 ? 0 : $count;
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
