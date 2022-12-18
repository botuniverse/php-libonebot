<?php

declare(strict_types=1);

namespace OneBot\V12\Action;

use Choir\Http\Stream;
use OneBot\Util\FileUtil;
use OneBot\Util\Utils;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\Action;
use OneBot\V12\Object\ActionResponse;
use OneBot\V12\OneBot;
use OneBot\V12\RetCode;
use OneBot\V12\Validator;
use Psr\Http\Message\ResponseInterface;

abstract class ActionHandlerBase
{
    /** @internal 内部使用的缓存 */
    public static $core_cache;

    /** @internal 内部使用的缓存 */
    public static $ext_cache;

    /** @var array 缓存的文件段 */
    private static $upload_fragment = [];

    /**
     * get_status 响应
     *
     * @param Action $action Action 数据对象
     */
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

    /**
     * get_version 响应
     *
     * @param Action $action Action 数据对象
     */
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
     * get_supported_actions 响应
     *
     * @param Action $action Action 数据对象
     */
    public function onGetSupportedActions(Action $action): ActionResponse
    {
        $reflection = new \ReflectionClass($this);
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
                $headers = isset($action->params['headers']) && Utils::isAssocArray($action->params['headers']) ? $action->params['headers'] : [];
                $resp = $this->downloadFile($action->params['url'], $action->params['name'], $path, $headers, $action->params['sha256'] ?? null);
                if ($action->echo !== null) {
                    $resp->echo = $action->echo;
                }
                return $resp;
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

    public function onGetFile(Action $action, int $stream_type = ONEBOT_JSON): ActionResponse
    {
        Validator::validateParamsByAction($action, ['type' => ['url', 'path', 'data']]);
        [$meta, $content, $path, $file_id] = $this->makeGetFileBefore($action);
        switch ($action->params['type']) {
            case 'url':
            default:
                if (isset($meta['url'])) {
                    // url 在 meta 文件中，直接返回原链接
                    return ActionResponse::create($action)->ok(['name' => $meta['name'], 'url' => $meta['url'], 'headers' => $meta['headers'] ?? []]);
                }
                // TODO: 以后可以支持生成链接
                return ActionResponse::create($action)->fail(RetCode::UNSUPPORTED_PARAM, 'generating url for download is not supported yet');
            case 'path':
            case 'data':
                if ($content === null && isset($meta['url'])) {
                    // 这个文件是懒加载的，我们现在下载一下
                    // 验证是否指定了 Headers，为 Assoc 类型的数组
                    $headers = isset($meta['headers']) && Utils::isAssocArray($meta['headers']) ? $meta['headers'] : [];
                    $resp = $this->downloadFile($meta['url'], $meta['name'], $path, $headers, $meta['sha256'] ?? null, $file_id);
                    if ($resp->retcode === 0) {
                        if ($action->params['type'] === 'path') {
                            $final_file_path = FileUtil::getRealPath($path . '/' . $file_id);
                            $ret = ['name' => $meta['name'], 'path' => $final_file_path];
                        } else {
                            [$meta, $content] = FileUtil::getMetaFile($path, $file_id);
                            $ret = ['name' => $meta['name'], 'data' => $stream_type === ONEBOT_JSON ? base64_encode($content) : $content];
                        }
                        if (is_string($meta['sha256'] ?? null)) {
                            $ret['sha256'] = $meta['sha256'];
                        }
                        $resp->data = $ret;
                    }
                    $action->echo !== null && $resp->echo = $action->echo;
                    return $resp;
                }
                if ($content !== null) {
                    $final_file_path = FileUtil::getRealPath($path . '/' . $file_id);
                    $ret = ['name' => $meta['name'], 'path' => $final_file_path];
                    if (is_string($meta['sha256'] ?? null)) {
                        $ret['sha256'] = $meta['sha256'];
                    }
                    return ActionResponse::create($action)->ok($ret);
                }
                return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR, 'file source data not found');
        }
    }

    public function onGetFileFragmented(Action $action, int $stream_type = ONEBOT_JSON): ActionResponse
    {
        [$meta, $content, $path, $file_id] = $this->makeGetFileBefore($action);
        Validator::validateParamsByAction($action, ['stage' => ['prepare', 'transfer']]);
        switch ($action->params['stage']) {
            case 'prepare':
            default:
                if ($content === null && isset($meta['url'])) {
                    // 这个文件是懒加载的，我们现在下载一下
                    // 验证是否指定了 Headers，为 Assoc 类型的数组
                    $headers = isset($meta['headers']) && Utils::isAssocArray($meta['headers']) ? $meta['headers'] : [];
                    $resp = $this->downloadFile($meta['url'], $meta['name'], $path, $headers, $meta['sha256'] ?? null, $file_id);
                    if ($resp->retcode === 0) {
                        [$meta] = FileUtil::getMetaFile($path, $file_id);
                        $resp->data = [
                            'name' => $meta['name'],
                            'total_size' => filesize(FileUtil::getRealPath($path . '/' . $file_id)),
                            'sha256' => hash_file('sha256', FileUtil::getRealPath($path . '/' . $file_id)),
                        ];
                    }
                    $action->echo !== null && $resp->echo = $action->echo;
                    return $resp;
                }
                if ($content !== null) {
                    $ret = [
                        'name' => $meta['name'],
                        'total_size' => filesize(FileUtil::getRealPath($path . '/' . $file_id)),
                        'sha256' => hash_file('sha256', FileUtil::getRealPath($path . '/' . $file_id)),
                    ];
                    return ActionResponse::create($action)->ok($ret);
                }
                return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR, 'file source data not found');
            case 'transfer':
                if ($content === null) {
                    return ActionResponse::create($action)->fail(RetCode::LOGIC_ERROR, 'please prepare first');
                }
                Validator::validateParamsByAction($action, ['offset' => ONEBOT_TYPE_INT, 'size' => ONEBOT_TYPE_INT]);
                $s = Stream::create($content);
                $s->seek($action->params['offset']);
                return ActionResponse::create($action)->ok(['data' => $stream_type === ONEBOT_JSON ? base64_encode($s->read($action->params['size'])) : $s->read($action->params['size'])]);
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

    private function makeGetFileBefore(Action $action)
    {
        Validator::validateParamsByAction($action, ['file_id' => ONEBOT_TYPE_STRING]);
        $path = ob_config('file_upload.path', getcwd() . '/data/files');
        if (FileUtil::isRelativePath($path)) {
            $path = FileUtil::getRealPath(getcwd() . '/' . $path);
        }
        // 防止任意文件读取漏洞
        $file_id = $action->params['file_id'];
        if (strpos($file_id, '/') !== false || strpos($file_id, '..') !== false) {
            return ActionResponse::create($action)->fail(RetCode::BAD_PARAM);
        }
        [$meta, $content] = FileUtil::getMetaFile($path, $file_id);
        if ($meta === null) {
            return ActionResponse::create($action)->fail(RetCode::FILESYSTEM_ERROR, 'file metadata not found: ' . $file_id);
        }
        return [$meta, $content, $path, $file_id];
    }

    private function downloadFile(string $url, string $name, string $path, array $headers = [], ?string $sha256 = null, ?string $file_id = null): ActionResponse
    {
        // 验证 url 是否合法（即必须保证是 http(s) 开头）
        Validator::validateHttpUrl($url);
        // 生成临时的 HttpClientSocket
        $sock = OneBot::getInstance()->getDriver()->createHttpClientSocket([
            'url' => $url,
            'headers' => $headers,
            'timeout' => 30,
        ]);
        // 仅允许同步执行
        return $sock->withoutAsync()->get([], function (ResponseInterface $response) use ($url, $name, $path, $headers, $sha256, $file_id) {
            if ($response->getStatusCode() !== 200) {
                // 非200状态码认为是无效的下载，返回网络错误
                return ActionResponse::create()->fail(RetCode::NETWORK_ERROR, 'Return code is ' . $response->getReasonPhrase());
            }
            // 文件ID为文件内容计算md5得来
            if ($file_id === null) {
                $file_id = md5($response->getBody()->getContents());
            }
            $file_status = FileUtil::saveMetaFile($path, $file_id, $response->getBody(), [
                'name' => $name,
                'url' => $url,
                'headers' => $headers,
                'sha256' => $sha256,
            ]);
            if ($file_status === false) {
                return ActionResponse::create()->fail(RetCode::FILESYSTEM_ERROR);
            }
            return ActionResponse::create()->ok(['file_id' => $file_id]);
        }, function ($request, $a = null) {
            if ($a instanceof \Throwable) {
                return ActionResponse::create()->fail(RetCode::NETWORK_ERROR, 'Request failed with ' . get_class($a) . ': ' . $a->getMessage() . "\n" . $a->getTraceAsString());
            }
            return ActionResponse::create()->fail(RetCode::NETWORK_ERROR, 'Request failed');
        });
    }
}
