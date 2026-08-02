<?php

declare(strict_types=1);

namespace Tests\OneBot\ObjectPool;

use OneBot\ObjectPool\AbstractObjectPool;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TestPool extends AbstractObjectPool
{
    protected function makeObject(): object
    {
        return new \stdClass();
    }
}

/**
 * 测试 AbstractObjectPool 在 SplQueue（Workerman 驱动）路径下的 return 不抛异常
 *
 * @internal
 */
class AbstractObjectPoolTest extends TestCase
{
    public function testReturnWithSplQueueDoesNotThrow()
    {
        // bootstrap 使用的是 Workerman 驱动，构造函数中会初始化 SplQueue
        $pool = new TestPool();
        $object = $pool->take();
        // SplQueue::push 返回 void，修复前在 strict_types=1 下会抛 TypeError
        $this->assertTrue($pool->return($object));
    }

    public function testTakeReturnsSameObjectAfterReturn()
    {
        $pool = new TestPool();
        $object = $pool->take();
        $pool->return($object);
        // 归还后再次取出，应取回同一个对象
        $this->assertSame($object, $pool->take());
    }
}
