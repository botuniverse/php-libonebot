<?php

declare(strict_types=1);

namespace Tests\OneBot\Driver;

use Choir\Http\HttpFactory;
use OneBot\Driver\Swoole\WebSocketClient as SwooleWebSocketClient;
use OneBot\Driver\Workerman\WebSocketClient as WorkermanWebSocketClient;
use PHPUnit\Framework\TestCase;

/**
 * 测试 WebSocketClient 的 URI 构造（不建立真实连接）
 *
 * @internal
 */
class WebSocketClientTest extends TestCase
{
    public function testSwooleClientFragmentUsesHash()
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('swoole extension not loaded');
        }
        $client = new SwooleWebSocketClient();
        // 通过反射注入 request，避免创建真实连接
        $request = HttpFactory::createRequest('GET', 'ws://example.com/path?q=1#frag');
        $request_prop = new \ReflectionProperty(SwooleWebSocketClient::class, 'request');
        if (PHP_VERSION_ID < 80100) {
            $request_prop->setAccessible(true);
        }
        $request_prop->setValue($client, $request);
        // 注入伪造的客户端，只记录 upgrade 的 URI，不进行真实连接
        $fake_client = new class {
            public $errCode = 0;

            public $errMsg = '';

            /** @var null|string */
            public $upgrade_uri;

            public function upgrade(string $uri)
            {
                $this->upgrade_uri = $uri;
                return false;
            }
        };
        $client_prop = new \ReflectionProperty(SwooleWebSocketClient::class, 'client');
        if (PHP_VERSION_ID < 80100) {
            $client_prop->setAccessible(true);
        }
        $client_prop->setValue($client, $fake_client);

        $this->assertFalse($client->connect());
        // fragment 必须用 # 拼接，而不是 ?
        $this->assertSame('/path?q=1#frag', $fake_client->upgrade_uri);
    }

    public function testSwooleClientNoQueryNoFragment()
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('swoole extension not loaded');
        }
        $client = new SwooleWebSocketClient();
        $request = HttpFactory::createRequest('GET', 'ws://example.com/');
        $request_prop = new \ReflectionProperty(SwooleWebSocketClient::class, 'request');
        if (PHP_VERSION_ID < 80100) {
            $request_prop->setAccessible(true);
        }
        $request_prop->setValue($client, $request);
        $fake_client = new class {
            public $errCode = 0;

            public $errMsg = '';

            /** @var null|string */
            public $upgrade_uri;

            public function upgrade(string $uri)
            {
                $this->upgrade_uri = $uri;
                return false;
            }
        };
        $client_prop = new \ReflectionProperty(SwooleWebSocketClient::class, 'client');
        if (PHP_VERSION_ID < 80100) {
            $client_prop->setAccessible(true);
        }
        $client_prop->setValue($client, $fake_client);

        $client->connect();
        $this->assertSame('/', $fake_client->upgrade_uri);
    }

    public function testWorkermanClientDefaultPortWhenNotSpecified()
    {
        $client = new WorkermanWebSocketClient();
        $request = HttpFactory::createRequest('GET', 'ws://example.com/path');
        $client->withRequest($request);
        $connection = $this->getConnection($client);
        $this->assertSame(80, $this->getPrivateValue($connection, '_remotePort'));
        $this->assertSame('example.com', $this->getPrivateValue($connection, '_remoteHost'));
    }

    public function testWorkermanClientKeepsExplicitPort()
    {
        $client = new WorkermanWebSocketClient();
        $request = HttpFactory::createRequest('GET', 'ws://example.com:8080/path');
        $client->withRequest($request);
        $connection = $this->getConnection($client);
        $this->assertSame(8080, $this->getPrivateValue($connection, '_remotePort'));
    }

    private function getConnection(WorkermanWebSocketClient $client)
    {
        $prop = new \ReflectionProperty(WorkermanWebSocketClient::class, 'connection');
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        return $prop->getValue($client);
    }

    private function getPrivateValue($object, string $name)
    {
        $prop = new \ReflectionProperty($object, $name);
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        return $prop->getValue($object);
    }
}
