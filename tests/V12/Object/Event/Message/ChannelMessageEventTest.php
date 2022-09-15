<?php

declare(strict_types=1);

namespace Tests\V12\Object\Event\Message;

use OneBot\V12\Object\Event\Message\ChannelMessageEvent;
use OneBot\V12\Object\MessageSegment;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ChannelMessageEventTest extends TestCase
{
    public function testSerialize()
    {
        $obj = new ChannelMessageEvent('123456', '888888', '123444', MessageSegment::text('whoami'));
        $array = json_decode(json_encode($obj), true);
        $this->assertEquals('123456', $array['guild_id']);
        $this->assertEquals('888888', $array['channel_id']);
        $this->assertEquals('whoami', $array['message'][0]['data']['text']);
    }
}
