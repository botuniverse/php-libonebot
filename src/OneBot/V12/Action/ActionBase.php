<?php

namespace OneBot\V12\Action;

use OneBot\V12\Object\ActionObject;
use OneBot\V12\OneBot;
use OneBot\V12\RetCode;
use OneBot\V12\Utils;

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

    public function onGetLatestEvents(ActionObject $action): ActionResponse {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetSupportedActions(ActionObject $action): ActionResponse {
        $reflection = new \ReflectionClass($this);
        $list = [];
        foreach ($reflection->getMethods() as $v) {
            $sep = Utils::camelToSeparator($v->getName());
            if (substr($sep, 0, 3) === 'on_') {
                $list[] = substr($sep, 3);
            } elseif (substr($sep, 0, 4) === 'ext_') {
                $list[] = OneBot::getInstance()->getPlatform() . '.' . substr($sep, 4);
            }
        }
        return ActionResponse::create($action->echo)->ok($list);
    }
}