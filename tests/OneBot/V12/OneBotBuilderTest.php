<?php

declare(strict_types=1);

namespace Tests\OneBot\V12;

use OneBot\Driver\Driver;
use OneBot\Driver\DriverEventLoopBase;
use OneBot\Driver\Socket\HttpClientSocketBase;
use OneBot\V12\OneBotBuilder;
use PHPUnit\Framework\TestCase;
use ZM\Logger\ConsoleLogger;

/**
 * 测试用假驱动：WorkermanDriver 不能重复实例化，测试构建顺序时使用
 */
class FakeDriver extends Driver
{
    public function run(): void {}

    public function getName(): string
    {
        return 'fake';
    }

    public function getEventLoop(): DriverEventLoopBase
    {
        return FakeEventLoop::getInstance();
    }

    public function initWSReverseClients(array $headers = []) {}

    public function createHttpClientSocket(array $config): HttpClientSocketBase
    {
        throw new \RuntimeException('not implemented in fake driver');
    }

    protected function initInternalDriverClasses(?array $http, ?array $http_webhook, ?array $ws, ?array $ws_reverse): array
    {
        return [];
    }
}

/**
 * 测试用假事件循环
 */
class FakeEventLoop extends DriverEventLoopBase
{
    public function addReadEvent($fd, callable $callable) {}

    public function delReadEvent($fd) {}

    public function addWriteEvent($fd, callable $callable) {}

    public function delWriteEvent($fd) {}

    public function addTimer(int $ms, callable $callable, int $times = 1, array $arguments = []): int
    {
        return 0;
    }

    public function clearTimer(int $timer_id) {}

    public function clearAllTimer() {}
}

/**
 * 测试 OneBotBuilder 对组件配置顺序不敏感
 *
 * @internal
 */
class OneBotBuilderTest extends TestCase
{
    public function testBuildWithDifferentConfigOrder()
    {
        // bootstrap 已构建过一个 OneBot 单例，走到 new OneBot() 时会抛出"只能有一个OneBot实例"，
        // 只要不是抛 Builder 配置缺失异常，就说明键顺序不再敏感
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('只能有一个OneBot实例！');
        OneBotBuilder::factory()
            ->setSelfId('t002')
            ->setPlatform('testarea')
            ->useDriver(FakeDriver::class)
            ->setCommunicationsProtocol([['http' => ['host' => '0.0.0.0', 'port' => 20001]]])
            ->setName('test2')
            ->useLogger(ConsoleLogger::class)
            ->build();
    }

    public function testBuildWithAnotherOrder()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('只能有一个OneBot实例！');
        OneBotBuilder::factory()
            ->useLogger(ConsoleLogger::class)
            ->setPlatform('testarea')
            ->setName('test3')
            ->setSelfId('t003')
            ->setCommunicationsProtocol([['http' => ['host' => '0.0.0.0', 'port' => 20001]]])
            ->useDriver(FakeDriver::class)
            ->build();
    }

    public function testBuildMissingComponentStillThrows()
    {
        // 缺少组件时仍要抛出带 missing 提示的异常
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing');
        OneBotBuilder::factory()
            ->setName('test4')
            ->setPlatform('testarea')
            ->useLogger(ConsoleLogger::class)
            ->build();
    }
}
