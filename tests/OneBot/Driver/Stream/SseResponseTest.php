<?php

declare(strict_types=1);

namespace Tests\OneBot\Driver\Stream;

use OneBot\Driver\Interfaces\StreamSinkInterface;
use OneBot\Driver\Stream\SseResponse;
use PHPUnit\Framework\TestCase;

/**
 * SSE 帧格式与流式写出的单元测试
 *
 * 规范参考：https://html.spec.whatwg.org/multipage/server-sent-events.html
 */
class SseResponseTest extends TestCase
{
    public function testSimpleData(): void
    {
        self::assertSame("data: hello\n\n", SseResponse::format('hello'));
    }

    public function testMultilineDataBecomesMultipleDataLines(): void
    {
        // 多行必须一行一个 data:，否则浏览器只认第一行
        self::assertSame("data: a\ndata: b\n\n", SseResponse::format("a\nb"));
    }

    public function testArrayIsJsonEncoded(): void
    {
        self::assertSame(
            'data: {"seq":1,"name":"测试"}' . "\n\n",
            SseResponse::format(['seq' => 1, 'name' => '测试'])
        );
    }

    public function testEventIdRetryAndOrder(): void
    {
        self::assertSame(
            "id: 7\nevent: token\nretry: 3000\ndata: x\n\n",
            SseResponse::format('x', 'token', '7', 3000)
        );
    }

    public function testNullBecomesEmptyPayload(): void
    {
        self::assertSame("data: \n\n", SseResponse::format(null));
    }

    public function testEventNameCannotInjectNewline(): void
    {
        self::assertSame("event: xevil\ndata: y\n\n", SseResponse::format('y', "x\nevil"));
    }

    public function testWriterReceivesChunksThroughSink(): void
    {
        $response = SseResponse::create(static function (StreamSinkInterface $sink, SseResponse $res): void {
            $res->send($sink, 'one', 'tick');
            $res->comment($sink, 'hb');
        });

        $sink = new class implements StreamSinkInterface {
            public array $chunks = [];

            public bool $closed = false;

            public function write(string $chunk): bool
            {
                $this->chunks[] = $chunk;
                return true;
            }

            public function end(): void
            {
                $this->closed = true;
            }

            public function isClosed(): bool
            {
                return $this->closed;
            }
        };

        $response->writeTo($sink);

        self::assertSame(["event: tick\ndata: one\n\n", ": hb\n\n"], $sink->chunks);
        self::assertFalse($sink->isClosed());
        self::assertTrue($response->hasHeader('Content-Type'));
        self::assertSame('text/event-stream; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no', $response->getHeaderLine('X-Accel-Buffering'));
    }

    public function testDisconnectStopsProduction(): void
    {
        $produced = 0;
        $response = SseResponse::create(static function (StreamSinkInterface $sink) use (&$produced): void {
            for ($i = 0; $i < 100; ++$i) {
                ++$produced;
                if (!$sink->write("data: {$i}\n\n")) {
                    return; // 对端断开后必须立刻停止，这里就是验证点
                }
            }
        });

        $sink = new class implements StreamSinkInterface {
            public bool $closed = false;

            public function write(string $chunk): bool
            {
                return false; // 模拟第一个 chunk 就写失败
            }

            public function end(): void
            {
                $this->closed = true;
            }

            public function isClosed(): bool
            {
                return $this->closed;
            }
        };

        $response->writeTo($sink);

        self::assertSame(1, $produced, '写失败后应立即停止生产，不能继续跑完 100 次');
    }
}
