<?php

declare(strict_types=1);

namespace OneBot\V12\Action;

use OneBot\Util\Utils;
use OneBot\V12\Object\Action;
use OneBot\V12\OneBot;
use OneBot\V12\RetCode;
use ReflectionClass;

abstract class ActionBase
{
    /** @internal 内部使用的缓存 */
    public static $core_cache;

    /** @internal 内部使用的缓存 */
    public static $ext_cache;

    public function onSendMessage(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onDeleteMessage(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetStatus(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetVersion(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetSelfInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetUserInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetFriendList(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupList(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupMemberList(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupMemberInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetLatestEvents(Action $action): ActionResponse
    {
        return ActionResponse::create($action->echo)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    /**
     * 内置的一个可以使用的 API，用来获取所有已注册成功的 action
     */
    public function onGetSupportedActions(Action $action): ActionResponse
    {
        $reflection = new ReflectionClass($this);
        $list = [];
        foreach (OneBot::getInstance()->getActionHandlers() as $k => $v) {
            $list[] = $k;
        }
        foreach ($reflection->getMethods() as $v) {
            $sep = Utils::camelToSeparator($v->getName());
            if (strpos($sep, 'on_') === 0) {
                $list[] = substr($sep, 3);
            } elseif (strpos($sep, 'ext_') === 0) {
                $list[] = OneBot::getInstance()->getPlatform() . '.' . substr($sep, 4);
            }
        }
        return ActionResponse::create($action->echo)->ok($list);
    }
}
