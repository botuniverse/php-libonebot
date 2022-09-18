<?php

declare(strict_types=1);

namespace OneBot\V12\Action;

use OneBot\Http\Stream;
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

    /** @var array 缓存的文件段 */
    private static $upload_fragment = [];

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
        Validator::validateParamsByAction($action, ['type' => ONEBOT_TYPE_STRING, 'name' => ONEBOT_TYPE_STRING]);
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
                Validator::validateParamsByAction($action, ['url' => ONEBOT_TYPE_STRING]);
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
                        // 非200状态码认为是无效的下载，返回网络错误
                        return ActionResponse::create($action)->fail(RetCode::NETWORK_ERROR, 'Return code is ' . $response->getReasonPhrase());
                    }
                    // 文件ID为文件内容计算md5得来
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
                Validator::validateParamsByAction($action, ['path' => ONEBOT_TYPE_STRING]);
                $from_path = $action->params['path'];
                if (!file_exists($from_path = FileUtil::getRealPath($from_path))) {
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

    public function onUploadFileFragmented(Action $action, int $stream_type = ONEBOT_JSON): ActionResponse
    {
        Validator::validateParamsByAction($action, ['stage' => ['prepare', 'transfer', 'finish']]);
        // 默认路径
        $path = ob_config('file_upload.path', getcwd() . '/data/files');
        if (FileUtil::isRelativePath($path)) {
            $path = FileUtil::getRealPath(getcwd() . '/' . $path);
        }
        switch ($action->params['stage']) {
            case 'prepare': // 准备阶段
            default:
                Validator::validateParamsByAction($action, ['name' => ONEBOT_TYPE_STRING, 'total_size' => ONEBOT_TYPE_INT]);
                if (strpos($action->params['name'], '/') !== false || strpos($action->params['name'], '..') !== false) {
                    return ActionResponse::create($action)->fail(RetCode::BAD_PARAM);
                }
                if (!is_int($action->params['total_size']) || $action->params['total_size'] <= 0) {
                    return ActionResponse::create($action)->fail(RetCode::BAD_PARAM);
                }
                // 文件ID无法通过文件内容算出来，就通过时间戳获取一个文件ID
                $file_id = md5(strval(microtime(true)));
                // 缓存段
                self::$upload_fragment[$file_id] = [
                    'name' => $action->params['name'],
                    'total_size' => $action->params['total_size'],
                    'cache' => [],
                    'stream' => Stream::create(),
                ];
                // 返回文件ID
                return ActionResponse::create($action)->ok(['file_id' => $file_id]);
            case 'transfer': // 传输阶段
                // 先验证
                Validator::validateParamsByAction($action, ['file_id' => ONEBOT_TYPE_STRING, 'offset' => ONEBOT_TYPE_INT, 'data' => true]);
                if (!isset(self::$upload_fragment[$action->params['file_id']])) {
                    // 如果还没有prepare的话，返回错误
                    return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR, 'file id ' . $action->params['file_id'] . ' not found or not prepared yet');
                }
                $file_id = $action->params['file_id'];
                $data = $stream_type === ONEBOT_JSON ? base64_decode($action->params['data']) : $action->params['data'];
                // 额外的验证，如果数据的长度和传入的size不一致，那么就给个warning
                if (isset($action->params['size']) && $action->params['size'] !== strlen($data)) {
                    ob_logger_registered() && ob_logger()->warning('分段传输传入的size值和data本身的长度不一致，将取小值！');
                    $action->params['size'] = min($action->params['size'], strlen($data));
                    $data = substr($data, 0, $action->params['size']);
                }
                // 如果偏移量在 stream 范围内，直接写到 stream 里面
                /** @var Stream $stream */
                $stream = self::$upload_fragment[$file_id]['stream'];
                if ($action->params['offset'] <= $stream->getSize()) {
                    // 如果传入的偏移正好接到 stream 上，就直接写入
                    $stream->seek($action->params['offset']);
                    $stream->write($data);
                    // 检查下缓存，如果有乱序的看看能不能把后面的接上
                    ksort(self::$upload_fragment[$file_id]['cache']);
                    while (($offset = array_key_first(self::$upload_fragment[$file_id]['cache'])) !== null && $offset <= $stream->getSize()) {
                        $stream->seek($offset);
                        $stream->write(self::$upload_fragment[$file_id]['cache'][$offset]);
                        unset(self::$upload_fragment[$file_id]['cache'][$offset]);
                    }
                } else {
                    // 传入的 offset 比 stream 长度要长，说明乱序了，要先缓存起来
                    self::$upload_fragment[$file_id]['cache'][$action->params['offset']] = $data;
                }
                return ActionResponse::create($action)->ok();
            case 'finish': // 结束阶段
                Validator::validateParamsByAction($action, ['file_id' => ONEBOT_TYPE_STRING, 'sha256' => ONEBOT_TYPE_STRING]);
                if (!isset(self::$upload_fragment[$action->params['file_id']])) {
                    // 如果还没有prepare的话，返回错误
                    return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR, 'file id ' . $action->params['file_id'] . ' not found or not prepared yet');
                }
                $file_id = $action->params['file_id'];
                // 首先验证文件是不是全的
                $total = self::$upload_fragment[$file_id]['total_size'];
                /** @var Stream $stream */
                $stream = self::$upload_fragment[$file_id]['stream'];
                $stream->seek(0);
                if ($total !== $stream->getSize()) {
                    unset(self::$upload_fragment[$file_id]);
                    return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR, 'file is not transferred completely yet or transfer too much');
                }
                if ($action->params['sha256'] !== hash('sha256', $stream->getContents())) {
                    unset(self::$upload_fragment[$file_id]);
                    return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR, 'file is invalid due to incorrect sha256 hash value');
                }
                $file_status = FileUtil::saveMetaFile($path, $file_id, $stream, [
                    'name' => self::$upload_fragment[$file_id]['name'],
                    'sha256' => $action->params['sha256'],
                ]);
                unset(self::$upload_fragment[$file_id]);
                if ($file_status === false) {
                    return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR);
                }
                return ActionResponse::create($action)->ok(['file_id' => $file_id]);
        }
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
