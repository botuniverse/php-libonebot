<?php

declare(strict_types=1);

namespace OneBot\V12\Action;

use OneBot\Util\FileUtil;
use OneBot\Util\Utils;
use OneBot\V12\Object\Action;
use OneBot\V12\Object\ActionResponse;
use OneBot\V12\OneBot;
use OneBot\V12\RetCode;
use OneBot\V12\Validator;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use Throwable;

abstract class ActionHandlerBase
{
    /** @internal 内部使用的缓存 */
    public static $core_cache;

    /** @internal 内部使用的缓存 */
    public static $ext_cache;

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

    public function onUploadFile(Action $action, int $stream_type = ONEBOT_JSON): ActionResponse
    {
        // 验证上传文件必需的两个参数是否存在
        Validator::validateParamsByAction($action, ['type' => true, 'name' => true]);
        if (strpos($action->params['name'], '/') !== false || strpos($action->params['name'], '..') !== false) {
            return ActionResponse::create($action)->fail(RetCode::BAD_PARAM);
        }
        $path = ob_config('file_upload.path', getcwd() . '/data/files');
        if (FileUtil::isRelativePath($path)) {
            $path = FileUtil::getRealPath(getcwd() . '/' . $path);
        }
        switch ($action->params['type']) {
            case 'url':
                // url上传类型必须包含url参数
                Validator::validateParamsByAction($action, ['url' => true]);
                // 验证是否指定了 Headers，为 Assoc 类型的数组
                if (isset($action->params['headers']) && Utils::isAssocArray($action->params['headers'])) {
                    $headers = $action->params['headers'];
                }
                // 验证 url 是否合法（即必须保证是 http(s) 开头）
                Validator::validateHttpUrl($action->params['url']);
                // 生成临时的 HttpClientSocket
                $sock = OneBot::getInstance()->getDriver()->createHttpClientSocket([
                    'url' => $action->params['url'],
                    'headers' => $headers ?? [],
                    'timeout' => 30,
                ]);
                // 仅允许同步执行
                return $sock->withoutAsync()->get([], function (ResponseInterface $response) use ($action, $path) {
                    if ($response->getStatusCode() !== 200) {
                        return ActionResponse::create($action)->fail(RetCode::NETWORK_ERROR, 'Return code is ' . $response->getReasonPhrase());
                    }
                    $file_id = md5($response->getBody()->getContents());
                    $file_status = FileUtil::saveMetaFile($path, $file_id, $response->getBody(), [
                        'name' => $action->params['name'],
                        'url' => $action->params['url'],
                        'sha256' => $action->params['sha256'] ?? null,
                    ]);
                    if ($file_status === false) {
                        return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR);
                    }
                    return ActionResponse::create($action)->ok(['file_id' => $file_id]);
                }, function ($request, $a = null) use ($action) {
                    if ($a instanceof Throwable) {
                        return ActionResponse::create($action)->fail(RetCode::NETWORK_ERROR, 'Request failed with ' . get_class($a) . ': ' . $a->getMessage() . "\n" . $a->getTraceAsString());
                    }
                    return ActionResponse::create($action)->fail(RetCode::NETWORK_ERROR, 'Request failed');
                });
            case 'path':
                Validator::validateParamsByAction($action, ['path' => true]);
                $from_path = $action->params['path'];
                if (!is_string($from_path) || !file_exists($from_path = FileUtil::getRealPath($from_path))) {
                    return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR, 'file not found for path: ' . $from_path);
                }
                $file_id = md5_file($from_path);
                if ($file_id === false) {
                    return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR, 'file cannot calculate md5: ' . $from_path);
                }
                $file_status = FileUtil::saveMetaFile($path, $file_id, null, [
                    'name' => $action->params['name'],
                    'sha256' => $action->params['sha256'] ?? null,
                ]);
                if (!copy($from_path, $path . '/' . $file_id)) {
                    return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR, 'file copy failed from ' . $from_path);
                }
                if ($file_status === false) {
                    return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR);
                }
                return ActionResponse::create($action)->ok(['file_id' => $file_id]);
            case 'data':
                Validator::validateParamsByAction($action, ['data' => true]);
                $data = $stream_type === ONEBOT_JSON ? base64_decode($action->params['data']) : $action->params['data'];
                if ($data === false) {
                    return ActionResponse::create($action)->fail(RetCode::BAD_PARAM, 'input base64 data is invalid');
                }
                $file_id = md5($data);
                $file_status = FileUtil::saveMetaFile($path, $file_id, $data, [
                    'name' => $action->params['name'],
                    'sha256' => $action->params['sha256'] ?? null,
                ]);
                if ($file_status === false) {
                    return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR);
                }
                return ActionResponse::create($action)->ok(['file_id' => $file_id]);
        }
        return ActionResponse::create($action)->fail(RetCode::BAD_PARAM);
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
