<?php

declare(strict_types=1);

namespace Tests\OneBot\V12;

use MessagePack\MessagePack;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Http\HttpFactory;
use OneBot\V12\Action\ActionResponse;
use OneBot\V12\Object\Action;
use OneBot\V12\OneBot;
use OneBot\V12\OneBotEventListener;
use OneBot\V12\RetCode;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class OneBotEventListenerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        OneBot::getInstance()->addActionHandler('test', function (Action $obj) {
            return ActionResponse::create($obj->echo)->ok(['hello' => 'world']);
        });
    }

    /**
     * @dataProvider providerOnHttpRequest
     */
    public function testOnHttpRequest(array $request_params, array $expected)
    {
        $req = HttpFactory::getInstance()->createServerRequest(...$request_params);
        $event = new HttpRequestEvent($req);
        $event->setSocketFlag(1);
        OneBotEventListener::getInstance()->onHttpRequest($event);
        if ($event->getResponse()->getHeaderLine('content-type') === 'application/msgpack') {
            $obj = MessagePack::unpack($event->getResponse()->getBody()->getContents());
        } else {
            $obj = json_decode($event->getResponse()->getBody()->getContents(), true);
        }
        foreach ($expected as $k => $v) {
            switch ($k) {
                case 'status_code':
                    $this->assertEquals($v, $event->getResponse()->getStatusCode());
                    break;
                case 'retcode':
                    $this->assertArrayHasKey('retcode', $obj);
                    $this->assertEquals($v, $obj['retcode']);
                    break;
                case 'echo':
                    $this->assertEquals($v, $obj['echo']);
                    break;
                case 'data_contains_key':
                    $this->assertArrayHasKey($v, $obj['data']);
                    break;
            }
        }
    }

    public function providerOnHttpRequest(): array
    {
        return [
            'favicon 404' => [['GET', '/favicon.ico', [], null, '1.1', []], ['status_code' => 404]],
            'other header 404' => [['GET', '/waefawef', ['Content-Type' => 'text/html'], null, '1.1', []], ['status_code' => 200]],
            'default ok action' => [['POST', '/test', ['Content-Type' => 'application/json'], '{"action":"get_supported_actions"}'], ['status_code' => 200, 'retcode' => RetCode::OK]],
            'dynamic input action' => [['POST', '/test', ['Content-Type' => 'application/json'], '{"action":"test","echo":"hello world"}'], ['status_code' => 200, 'retcode' => RetCode::OK, 'echo' => 'hello world', 'data_contains_key' => 'hello']],
            'msgpack' => [['POST', '/test', ['Content-Type' => 'application/msgpack'], MessagePack::pack(['action' => 'get_supported_actions'])], ['status_code' => 200, 'retcode' => RetCode::OK]],
            'json no action' => [['POST', '/test', ['Content-Type' => 'application/json'], '[]'], ['status_code' => 200, 'retcode' => RetCode::BAD_REQUEST]],
        ];
    }
}
