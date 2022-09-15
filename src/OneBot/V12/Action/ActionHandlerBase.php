<?php

declare(strict_types=1);

namespace OneBot\V12\Action;

use OneBot\Util\Utils;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\Action;
use OneBot\V12\OneBot;
use OneBot\V12\RetCode;
use OneBot\V12\Validator;
use ReflectionClass;

abstract class ActionHandlerBase
{
    /** @internal 内部使用的缓存 */
    public static $core_cache;

    /** @internal 内部使用的缓存 */
    public static $ext_cache;

    public function __call(string $name, $values)
    {
        $unsupported_list = [
        ];
        if (in_array($name, $unsupported_list) && (($values[0] ?? null) instanceof Action)) {
            return ActionResponse::create($values[0])->fail(RetCode::UNSUPPORTED_ACTION);
        }
        throw new OneBotFailureException();
    }

    public function onGetStatus(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->ok([
            'good' => OneBot::getInstance()->getBotStatus(),
            'bots' => [
                [
                    'self' => [
                        'platform' => OneBot::getInstance()->getPlatform(),
                        'user_id' => OneBot::getInstance()->getSelfId(),
                    ],
                    'online' => OneBot::getInstance()->getBotStatus(),
                ],
            ],
        ]);
    }

    public function onGetVersion(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->ok([
            'impl' => OneBot::getInstance()->getImplementName(),
            'version' => OneBot::getInstance()->getAppVersion(),
            'onebot_version' => '12',
        ]);
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
        ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
        return ActionResponse::create($action)->ok($list);
    }

    /**
     * @throws OneBotFailureException
     */
    public function onUploadFile(Action $action, int $stream_type): ActionResponse
    {
        Validator::validateParamsByAction($action, ['type' => true, 'name' => true]);
        switch ($action->params['type']) {
            case 'url':
                Validator::validateParamsByAction($action, ['url' => true]);
                if (isset($action->params['headers']) && Utils::isAssocArray($action->params['headers'])) {
                    $headers = $action->params['headers'];
                }
                Validator::validateHttpUrl($action->params['url']);
                // TODO: 继续编写上传文件的地方
                break;
        }
        return ActionResponse::create();
    }

    // 下面是所有 OneBot 12 标准的动作，默认全部返回未实现

    public function onSendMessage(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onDeleteMessage(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetSelfInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetUserInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetFriendList(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupList(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupMemberList(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGroupMemberInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onSetGroupName(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onLeaveGroup(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetLatestEvents(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGuildInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGuildList(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onSetGuildName(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGuildMemberInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetGuildMemberList(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onLeaveGuild(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetChannelInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetChannelList(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onSetChannelName(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetChannelMemberInfo(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onGetChannelMemberList(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }

    public function onLeaveChannel(Action $action): ActionResponse
    {
        return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_ACTION);
    }
}
