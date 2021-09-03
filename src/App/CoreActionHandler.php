<?php


namespace App;


use OneBot\V12\ActionResponse;
use OneBot\V12\CoreActionInterface;

class CoreActionHandler implements CoreActionInterface
{
    public function onSendMessage($params, $echo): ActionResponse {
        return ActionResponse::create($echo)->ok();
    }

    public function onDeleteMessage($params, $echo): ActionResponse {
        return ActionResponse::create($echo)->ok();
    }

    public function onGetStatus($params, $echo): ActionResponse {
        // TODO: Implement onGetStatus() method.
    }

    public function onGetVersion($params, $echo): ActionResponse {
        // TODO: Implement onGetVersion() method.
    }

    public function onGetSelfInfo($params, $echo): ActionResponse {
        // TODO: Implement onGetSelfInfo() method.
    }

    public function onGetUserInfo($params, $echo): ActionResponse {
        // TODO: Implement onGetUserInfo() method.
    }

    public function onGetFriendList($params, $echo): ActionResponse {
        // TODO: Implement onGetFriendList() method.
    }

    public function onGetGroupInfo($params, $echo): ActionResponse {
        // TODO: Implement onGetGroupInfo() method.
    }

    public function onGetGroupList($params, $echo): ActionResponse {
        // TODO: Implement onGetGroupList() method.
    }

    public function onGetGroupMemberList($params, $echo): ActionResponse {
        // TODO: Implement onGetGroupMemberList() method.
    }

    public function onGetGroupMemberInfo($params, $echo): ActionResponse {
        // TODO: Implement onGetGroupMemberInfo() method.
    }

    public function onGetLatestEvent($params, $echo): ActionResponse {
        // TODO: Implement onGetLatestEvent() method.
    }
}