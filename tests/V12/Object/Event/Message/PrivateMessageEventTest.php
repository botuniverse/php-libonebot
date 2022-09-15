<?php

declare(strict_types=1);

namespace Tests\V12\Object\Event\Message;

use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Event\Message\PrivateMessageEvent;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class PrivateMessageEventTest extends TestCase
{
    /**
     * @throws OneBotException
     */
    public function testSerialize()
    {
        $time = time();
        $obj = new PrivateMessageEvent('123', 'msg', $time);
        $obj->setExtendedDatum('message_prefix', 'CrazyBot');
        $array = json_decode(json_encode($obj), true);
        $this->assertArrayHasKey('user_id', $array);
        $this->assertEquals($time, $array['time']);
        $this->assertEquals('msg', $array['message'][0]['data']['text']);
        $this->assertEquals('t001', $array['self']['user_id']);
        $this->assertEquals('testarea', $array['self']['platform']);
        $this->assertEquals('message', $array['type']);
        $this->assertEquals('private', $array['detail_type']);
        $this->assertArrayHasKey('testarea.message_prefix', $array);
        $this->assertEquals('CrazyBot', $array['testarea.message_prefix']);
    }
}
