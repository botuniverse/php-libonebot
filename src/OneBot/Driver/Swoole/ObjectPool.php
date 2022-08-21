<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole;

use OneBot\Driver\Interfaces\PoolInterface;
use RuntimeException;
use Swoole\Coroutine\Channel;

class ObjectPool implements PoolInterface
{
    /** @var string 类名 */
    protected $class;

    /** @var array 构造类的参数 */
    protected $args;

    /** @var int 池大小 */
    protected $size;

    /** @var Channel Swoole 的 Channel 对象 */
    protected $channel;

    /** @var array 借出去的对象 Hash 表 */
    protected $out = [];

    /**
     * @param int    $size            池大小
     * @param string $construct_class 构造类名
     * @param mixed  ...$args         传入的参数
     */
    public function __construct(int $size, string $construct_class, ...$args)
    {
        $this->class = $construct_class;
        $this->args = $args;
        $this->size = $size;
        $this->channel = new Channel($size + 10);
    }

    public function __destruct()
    {
        while (!$this->channel->isEmpty()) {
            $this->channel->pop();
        }
        $this->channel->close();
        unset($this->channel);
    }

    /**
     * 获取对象
     */
    public function get(): object
    {
        if ($this->getFreeCount() <= 0) {       // 当池子见底了，就自动用 Swoole 的 Channel 消费者模型堵起来
            $result = $this->channel->pop();
        } elseif ($this->channel->isEmpty()) {  // 如果 Channel 是空的，那么就新建一个对象
            $result = $this->makeObject();
        } else {                                // 否则就直接从 Channel 中取一个出来
            $result = $this->channel->pop();
        }
        if (!$result) { // 当池子被关闭则抛出异常
            throw new RuntimeException('Channel has been disabled');
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
        return $this->channel->push($object);
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
