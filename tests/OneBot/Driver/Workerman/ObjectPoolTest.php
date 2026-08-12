<?php

declare(strict_types=1);

namespace Tests\OneBot\Driver\Workerman;

use OneBot\Driver\Coroutine\Adaptive;
use OneBot\Driver\Workerman\ObjectPool;
use OneBot\Driver\Workerman\WorkermanDriver;
use PHPUnit\Framework\TestCase;

/**
 * 测试 Workerman ObjectPool 的多池隔离（跨池不串对象）
 *
 * @internal
 */
class ObjectPoolTest extends TestCase
{
    protected function setUp(): void
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Fiber 协程仅在 PHP >= 8.1 可用');
        }
        // 初始化 Fiber 协程环境
        Adaptive::initWithDriver(WorkermanDriver::getInstance());
    }

    protected function tearDown(): void
    {
        // 恢复未初始化协程的状态，避免影响其他测试
        self::setCoroutineNull();
    }

    public function testMultiPoolNotMixObjects()
    {
        $pool_a = new ObjectPool(1, \stdClass::class);
        $pool_b = new ObjectPool(1, \stdClass::class);
        // 先从两个池子各取出一个对象，让两个池子的队列都为空
        $object_a = $pool_a->get();
        $object_b = $pool_b->get();

        // 在协程中等待池 A 归还对象，此时池 A 见底，协程会挂起
        $co = Adaptive::getCoroutine();
        $this->assertNotNull($co);
        $fiber_result = null;
        $cid = $co->create(function () use ($pool_a, &$fiber_result) {
            $fiber_result = $pool_a->get();
        });
        $this->assertTrue($co->exists($cid), '协程应在等待池 A 时挂起');

        // 先归还对象到池 B：修复前会错误地唤醒等待池 A 的协程，并给它池 B 的对象
        $this->assertTrue($pool_b->put($object_b));
        $this->assertTrue($co->exists($cid), '归还到池 B 不应唤醒等待池 A 的协程');

        // 归还对象到池 A，此时才应该唤醒等待中的协程，并且拿到的必须是池 A 的对象
        $this->assertTrue($pool_a->put($object_a));
        $this->assertFalse($co->exists($cid), '归还到池 A 后协程应已唤醒并结束');
        $this->assertSame($object_a, $fiber_result);
    }

    public function testPoolGetPutInSyncMode()
    {
        // 未初始化协程时（同步模式），get/put 应该走递归重试与队列路径，不抛异常
        self::setCoroutineNull();

        // 单对象往返：取出-归还-再取出，必须是同一个对象
        $pool = new ObjectPool(3, \stdClass::class);
        $obj = $pool->get();
        $this->assertTrue($pool->put($obj));
        $this->assertSame($obj, $pool->get());
        // 归还，释放一个名额，避免池子见底走等待路径
        $this->assertTrue($pool->put($obj));

        // 两个对象的往返：归还的对象必须还能被取回（SplQueue 的 pop 顺序依赖 PHP 版本，只校验归属）
        $obj1 = $pool->get();
        $obj2 = $pool->get();
        $this->assertNotSame($obj1, $obj2);
        $this->assertTrue($pool->put($obj1));
        $this->assertTrue($pool->put($obj2));
        $got1 = $pool->get();
        $got2 = $pool->get();
        $this->assertNotSame($got1, $got2);
        // 归还的对象必须还能被取回（SplQueue 的 pop 顺序依赖 PHP 版本，只校验归属）
        $this->assertTrue(in_array($got1, [$obj1, $obj2], true));
        $this->assertTrue(in_array($got2, [$obj1, $obj2], true));
    }

    public function testPutWithDeadCoroutineEnvironmentPushesBackToQueue()
    {
        $pool = new ObjectPool(1, \stdClass::class);
        $obj = $pool->get();
        $co = Adaptive::getCoroutine();
        $this->assertNotNull($co);
        // 协程等待池中对象，挂起并记录 coroutine_cid
        $cid = $co->create(function () use ($pool) {
            $pool->get();
        });
        $this->assertTrue($co->exists($cid), '协程应在等待对象时挂起');

        // 模拟协程环境被销毁（Adaptive::$coroutine 置空），等待协程无法被唤醒
        self::setCoroutineNull();

        // 修复前：对象被静默丢弃（既不入队也不唤醒）；修复后：对象必须回到空闲队列
        $this->assertTrue($pool->put($obj));
        $this->assertSame($obj, $pool->get(), '协程环境不可用时归还的对象不应丢失');

        // 清理：恢复协程环境并唤醒挂起的协程，避免 FiberCoroutine 静态 map 泄漏
        Adaptive::initWithDriver(WorkermanDriver::getInstance());
        $co->resume($cid, $obj);
        $this->assertFalse($co->exists($cid), '挂起的协程应已被唤醒并结束');
        $pool->put($obj);
    }

    private static function setCoroutineNull()
    {
        $prop = new \ReflectionProperty(Adaptive::class, 'coroutine');
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        $prop->setValue(null, null);
    }
}
