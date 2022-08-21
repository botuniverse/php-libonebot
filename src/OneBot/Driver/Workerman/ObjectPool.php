<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use OneBot\Driver\Coroutine\Adaptive;
use OneBot\Driver\Interfaces\PoolInterface;
use RuntimeException;
use SplQueue;

class ObjectPool implements PoolInterface
{
    /** @var string 类名 */
    protected $class;

    /** @var array 构造类的参数 */
    protected $args;

    /** @var int 池大小 */
    protected $size;

    /** @var SplQueue Swoole 的 Channel 对象 */
    protected $queue;

    /** @var array 借出去的对象 Hash 表 */
    protected $out = [];

    /** @var array 用于 Workerman Fiber 对接时保存协程 ID 的 */
    private static $coroutine_cid = [];

    public function __construct(int $size, string $construct_class, ...$args)
    {
        $this->class = $construct_class;
        $this->args = $args;
        $this->size = $size;
        $this->queue = new SplQueue();
    }

    public function __destruct()
    {
        while (!$this->queue->isEmpty()) {
            $this->queue->pop();
        }
        unset($this->queue);
    }

    public function get($recursive = 0): object
    {
        if ($this->getFreeCount() <= 0) {       // 当池子见底了，就自动用 Swoole 的 Channel 消费者模型堵起来
            if (($cid = Adaptive::getCoroutine()->getCid()) !== -1) {
                self::$coroutine_cid[] = $cid;
                $result = Adaptive::getCoroutine()->suspend();
            } elseif ($recursive <= 10) {
                Adaptive::sleep(1);
                return $this->get(++$recursive);
            } else {
                throw new RuntimeException('Non-coroutine mode cannot handle too much busy things');
            }
        } elseif ($this->queue->isEmpty()) {  // 如果 Channel 是空的，那么就新建一个对象
            $result = $this->makeObject();
        } else {                                // 否则就直接从 Channel 中取一个出来
            $result = $this->queue->pop();
        }
        // 记录借出去的 Hash 表
        $this->out[spl_object_hash($result)] = 1;
        return $result;
    }

    public function put(object $object): bool
    {
        if (!isset($this->out[spl_object_hash($object)])) {
            // 不能退还不是这里生产出去的对象
            throw new RuntimeException('Cannot put object that not got from here');
        }
        unset($this->out[spl_object_hash($object)]);
        if (!empty(self::$coroutine_cid)) {
            $cid = array_shift(self::$coroutine_cid);
            Adaptive::getCoroutine()->resume($cid, $object);
            return true;
        }
        try {
            $this->queue->push($object);
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    public function getFreeCount(): int
    {
        return $this->size - count($this->out);
    }

    protected function makeObject(): object
    {
        return new ($this->class)(...$this->args);
    }
}
