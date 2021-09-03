<?php


namespace OneBot\V12;


interface CoreActionInterface
{
    public function onSendMessage($params, $echo): ActionResponse;

    public function onDeleteMessage($params, $echo): ActionResponse;

    public function onGetStatus($params, $echo): ActionResponse;

    public function onGetVersion($params, $echo): ActionResponse;

    public function onGetSelfInfo($params, $echo): ActionResponse;

    public function onGetUserInfo($params, $echo): ActionResponse;

    public function onGetFriendList($params, $echo): ActionResponse;

    public function onGetGroupInfo($params, $echo): ActionResponse;

    public function onGetGroupList($params, $echo): ActionResponse;

    public function onGetGroupMemberList($params, $echo): ActionResponse;

    public function onGetGroupMemberInfo($params, $echo): ActionResponse;

    public function onGetLatestEvent($params, $echo): ActionResponse;
}