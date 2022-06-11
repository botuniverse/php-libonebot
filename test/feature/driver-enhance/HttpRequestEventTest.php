<?php

declare(strict_types=1);

namespace OneBot\Driver\Event\Http;

use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\EventProvider;
use OneBot\Http\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @internal
 * @coversNothing
 */
class HttpRequestEventTest extends TestCase
{
    public function testCustomErrorHandler()
    {
        $request = HttpFactory::getInstance()->createServerRequest('GET', 'http://www.baidu.com');
        EventProvider::addEventListener(HttpRequestEvent::getName(), function () {
            throw new \Exception('test exception');
        });
        $event = new HttpRequestEvent($request);
        $event->setErrorHandler(function () {
            return HttpFactory::getInstance()->createResponse();
        });
        $this->assertIsCallable($event->getErrorHandler());
        try {
            (new EventDispatcher())->dispatch($event);
        } catch (Throwable $e) {
            $this->assertIsCallable($event->getErrorHandler());
            $err_response = call_user_func($event->getErrorHandler(), $e, $event);
            $this->assertInstanceOf(ResponseInterface::class, $err_response);
        }
    }
}
