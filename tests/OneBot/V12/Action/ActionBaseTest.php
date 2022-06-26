<?php

declare(strict_types=1);

namespace Tests\OneBot\V12\Action;

use OneBot\V12\Action\ActionResponse;
use OneBot\V12\Action\DefaultActionHandler;
use OneBot\V12\Object\Action;
use OneBot\V12\RetCode;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ActionBaseTest extends TestCase
{
    private static $handler;

    public static function setUpBeforeClass(): void
    {
        self::$handler = new DefaultActionHandler();
    }

    public function testOnDeleteMessage()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onDeleteMessage(new Action('delete_message')));
    }

    public function testOnGetGroupMemberList()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onGetGroupMemberList(new Action('get_group_member_list')));
    }

    public function testOnGetSupportedActions()
    {
        $response = self::$handler->onGetSupportedActions(new Action('get_supported_actions'));
        $this->assertEquals('ok', $response->status);
        $this->assertEquals(0, $response->retcode);
        $this->assertNotEmpty($response->data);
    }

    public function testOnGetSelfInfo()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onGetSelfInfo(new Action('get_self_info')));
    }

    public function testOnGetLatestEvents()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onGetLatestEvents(new Action('get_latest_events')));
    }

    public function testOnGetVersion()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onGetVersion(new Action('get_version')));
    }

    public function testOnGetGroupList()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onGetGroupList(new Action('get_group_list')));
    }

    public function testOnGetGroupMemberInfo()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onGetGroupMemberInfo(new Action('get_group_member_info')));
    }

    public function testOnGetStatus()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onGetStatus(new Action('get_status')));
    }

    public function testOnGetFriendList()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onGetFriendList(new Action('get_friend_list')));
    }

    public function testOnGetGroupInfo()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onGetGroupInfo(new Action('get_group_info')));
    }

    public function testOnGetUserInfo()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onGetUserInfo(new Action('get_user_info')));
    }

    public function testOnSendMessage()
    {
        $this->assertEquals(ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION), self::$handler->onSendMessage(new Action('send_message')));
    }
}
