<?php

namespace OneBot\V12\Action;

use OneBot\V12\Object\ActionObject;
use OneBot\V12\RetCode;

abstract class ActionBase
{
    /** @internal 内部使用的缓存 */
    public static $core_cache;
    /** @internal 内部使用的缓存 */
    public static $ext_cache;

    public function onSendMessage(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onDeleteMessage(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetStatus(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetVersion(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetSelfInfo(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetUserInfo(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetFriendList(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupInfo(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupList(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupMemberList(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupMemberInfo(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetLatestEvent(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }
}