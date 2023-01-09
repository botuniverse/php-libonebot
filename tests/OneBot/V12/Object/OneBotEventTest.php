<?php

declare(strict_types=1);

namespace Tests\OneBot\V12\Object;

use OneBot\V12\Object\MessageSegment;
use OneBot\V12\Object\OneBotEvent;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class OneBotEventTest extends TestCase
{
    public function testGetMessageSegments(): void
    {
        $event = new OneBotEvent([
            'id' => '123',
            'type' => 'message',
            'self' => [
                'user_id' => '123',
                'platform' => 'test',
            ],
            'detail_type' => 'group',
            'sub_type' => 'normal',
            'time' => 123,
            'alt_message' => '123',
            'group_id' => '123',
            'user_id' => '123',
            'guild_id' => '123',
            'channel_id' => '123',
            'operator_id' => '123',
            'message_id' => '123',
            'message' => [
                [
                    'type' => 'text',
                    'data' => [
                        'text' => '123',
                    ],
                ],
            ],
        ]);
        $event->setMessage([ob_segment('mention', ['user_id' => '123456'])]);
        $this->assertInstanceOf(MessageSegment::class, $event->getMessage()[0]);
    }
}
