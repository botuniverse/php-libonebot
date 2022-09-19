<?php

declare(strict_types=1);

namespace Tests\OneBot\V12\Object\Event\Message;

use OneBot\V12\Object\Event\Message\GroupMessageEvent;
use OneBot\V12\Object\MessageSegment;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class GroupMessageEventTest extends TestCase
{
    public function testSerialize()
    {
        $obj = new GroupMessageEvent('123456', '888888', MessageSegment::text('whoami'));
        $array = json_decode(json_encode($obj), true);
        $this->assertEquals('123456', $array['group_id']);
        $this->assertEquals('888888', $array['user_id']);
        $this->assertEquals('whoami', $array['message'][0]['data']['text']);
    }
}
