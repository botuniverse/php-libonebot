<?php

declare(strict_types=1);

use OneBot\Driver\Event\EventProvider;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Swoole\SwooleDriver;
use OneBot\Http\HttpFactory;
use OneBot\V12\Action\ActionResponse;
use OneBot\V12\Object\Action;
use OneBot\V12\Object\Event\Message\PrivateMessageEvent;
use OneBot\V12\Object\MessageSegment;
use OneBot\V12\OneBot;
use OneBot\V12\OneBotBuilder;
use OneBot\V12\RetCode;
use OneBot\V12\Validator;
use Swoole\Coroutine\Channel;

require_once __DIR__ . '/../vendor/autoload.php';

$config = [
    'name' => 'onebot-mp-weixin',
    'platform' => 'weixin',
    'self_id' => '', // 后续会自动获取
    'db' => true,
    'logger' => [
        'class' => \ZM\Logger\ConsoleLogger::class,
        'level' => 'info',
    ],
    'driver' => [
        'class' => SwooleDriver::class,
        'config' => [
            'init_in_user_process_block' => true,
            'swoole_set' => [
                'max_coroutine' => 300000, // 默认如果不手动设置 Swoole 的话，提供的协程数量尽量多一些，保证并发性能（反正协程不要钱）
                'max_wait_time' => 5,      // 安全 shutdown 时候，让 Swoole 等待 Worker 进程响应的最大时间
                'worker_num' => 1,         // 启动一个 Worker 进程，因为微信公众号需要用到 Channel，所以这里只能启动一个 Worker 进程
            ],
            'workerman_worker_num' => 1,   // Workerman 启动 Worker 的数量，默认为 1，微信公众号不支持启动多个 Worker 进程，因为用到了 Async 回复
        ],
    ],
    'communications' => [
        [
            'type' => 'http',
            'host' => '0.0.0.0',
            'port' => 7776,
            'flag' => 1000,
        ],
    ],
    'wx' => [
        'token' => 'abcdefg', // 微信公众号设置的
        'aeskey' => 'USE12chars.!',
    ],
];

function message_id(): string
{
    return uniqid('', true);
}

/**
 * 检查微信公众号发来的 HTTP 请求是否合法
 *
 * @param array  $get   GET 请求参数
 * @param string $token 微信公众号设置的 token
 */
function check_wx_signature(array $get, string $token): bool
{
    $signature = $get['signature'] ?? '';
    $timestamp = $get['timestamp'] ?? '';
    $nonce = $get['nonce'] ?? '';
    $tmp_arr = [$token, $timestamp, $nonce];
    sort($tmp_arr, SORT_STRING);
    $tmp_str = implode($tmp_arr);
    $tmp_str = sha1($tmp_str);
    return $signature == $tmp_str;
}

function wx_global_get(string $key)
{
    global $wx_global;
    return $wx_global[$key] ?? null;
}

function wx_global_set(string $key, $value): void
{
    global $wx_global;
    $wx_global[$key] = $value;
}

function wx_global_unset(string $key): void
{
    global $wx_global;
    unset($wx_global[$key]);
}

function wx_global_isset(string $key): bool
{
    global $wx_global;
    return isset($wx_global[$key]);
}

function wx_make_xml_reply(Action $action, string $self_id): string
{
    // TODO: 用新闻页面支持多媒体文本消息
    $xml_template = "\n<xml><ToUserName>{user_id}</ToUserName><FromUserName>{from}</FromUserName><CreateTime>" . time() . '</CreateTime><MsgType>{type}</MsgType><Content>{content}</Content></xml>';
    $xml_template = str_replace('{user_id}', '<![CDATA[' . $action->params['user_id'] . ']]>', $xml_template);
    $xml_template = str_replace('{from}', '<![CDATA[' . $self_id . ']]>', $xml_template);
    $xml_template = str_replace('{type}', '<![CDATA[text]]>', $xml_template);
    $content = '';
    foreach ($action->params['message'] as $v) {
        if ($v['type'] !== 'text') {
            return str_replace('{content}', '<![CDATA[*含有多媒体消息，暂不支持*]]>', $xml_template);
        }
        $content .= $v['data']['text'];
    }

    return str_replace('{content}', '<![CDATA[' . $content . ']]>', $xml_template);
}

function swoole_channel(string $name, int $size = 1): Swoole\Coroutine\Channel
{
    global $channel;
    if (!isset($channel[$name])) {
        $channel[$name] = new Channel($size);
    }
    return $channel[$name];
}

$ob = OneBotBuilder::buildFromArray($config); // 传入通信方式

EventProvider::addEventListener(HttpRequestEvent::getName(), function (HttpRequestEvent $event) use ($config, $ob) {
    if ($event->getSocketFlag() !== 1000) {
        return;
    }

    // 检查是否为微信的签名
    if (!check_wx_signature($event->getRequest()->getQueryParams(), $config['wx']['token'])) {
        $event->withResponse(HttpFactory::getInstance()->createResponse(403));
        return;
    }

    // 如果是echostr认证，则直接返回
    if (isset($event->getRequest()->getQueryParams()['echostr'])) {
        $event->withResponse(HttpFactory::getInstance()->createResponse(200, null, [], $event->getRequest()->getQueryParams()['echostr']));
        return;
    }

    // 解析 XML 包体
    $xml_data = $event->getRequest()->getBody()->getContents();
    ob_logger()->info($xml_data);
    /** @noinspection PhpComposerExtensionStubsInspection */
    $xml_tree = new DOMDocument('1.0', 'utf-8');
    $xml_tree->loadXML($xml_data);
    $msg_type = $xml_tree->getElementsByTagName('MsgType')->item(0)->nodeValue;
    $self_id = $xml_tree->getElementsByTagName('ToUserName')->item(0)->nodeValue;
    if (OneBot::getInstance()->getSelfId() === '') {
        OneBot::getInstance()->setSelfId($self_id);
    }
    $user_id = $xml_tree->getElementsByTagName('FromUserName')->item(0)->nodeValue;
    switch ($msg_type) {
        case 'text':
            $content = $xml_tree->getElementsByTagName('Content')->item(0)->nodeValue;
            $msg_event = new PrivateMessageEvent($user_id, MessageSegment::createFromString($content));
            break;
        case 'image':
            $pic_url = $xml_tree->getElementsByTagName('PicUrl')->item(0)->nodeValue;
            /** @noinspection PhpComposerExtensionStubsInspection */
            $pic_url = openssl_encrypt($pic_url, 'AES-128-ECB', OneBot::getInstance()->getConfig()->get('wx.aeskey'));
            $seg = new MessageSegment('image', ['file_id' => $pic_url]);
            $msg_event = new PrivateMessageEvent($user_id, [$seg]);
            break;
        case 'event':
            $content = $xml_tree->getElementsByTagName('Event')->item(0)->nodeValue;
            break;
        case 'voice':
            $content = preg_replace('/[，。]/', '', $xml_tree->getElementsByTagName('Recognition')->item(0)->nodeValue);
            $msg_event = new PrivateMessageEvent($user_id, MessageSegment::createFromString($content));
            break;
        default:
            echo $xml_data . PHP_EOL;
    }

    if (!isset($msg_event)) {
        $event->withResponse(HttpFactory::getInstance()->createResponse(204));
        return;
    }
    // 设置 message_id，因为微信公众号事件中自带 MsgId，所以直接传递
    $msg_event->message_id = $xml_tree->getElementsByTagName('MsgId')->item(0)->nodeValue;

    // 然后分别判断 swoole 还是 workerman（处理方式不同
    // Swoole 处理时候直接用 Channel，这里为消费者，限定 4.5 秒内拿到一个回包，否则就不回复
    if ($ob->getDriver()->getName() === 'swoole') { // Swoole 用协程，因为 Swoole 下如果不用协程挂起的话，空返回直接 500
        if (swoole_channel($self_id . ':' . $user_id)->stats()['queue_num'] !== 0) {
            swoole_channel($self_id . ':' . $user_id)->pop(4.5);
        }
        wx_global_set($self_id . ':' . $user_id, true);
        // 首先调用 libob 内置的分发函数，通过不同的通信方式进行事件分发
        OneBot::getInstance()->dispatchEvent($msg_event);
        // 这段为临时的调试代码，模拟一个固定的发送消息动作
        /* switch ($msg_event->message[0]->type) {
            case 'text':
                $content = '收到了一条消息: ' . $msg_event->message[0]->data['text'];
                break;
            case 'image':
                $content = '123';
                break;
            case 'voice':
                $content = '收到了一条语音消息';
                break;
            default:
                $content = '没';
        }
        OneBotEventListener::getInstance()->processActionRequest(json_encode([
            'action' => 'send_message',
            'params' => [
                'user_id' => $user_id,
                'self_id' => $self_id,
                'detail_type' => 'private',
                'message' => [
                    [
                        'type' => 'text',
                        'data' => [
                            'text' => $content,
                        ],
                    ],
                ],
            ],
        ])); */
        $obj = swoole_channel($self_id . ':' . $user_id)->pop(4.5); // 等待4.5秒后，如果还不返回，就失败
        wx_global_unset($self_id . ':' . $user_id);
        if ($obj === false) {
            $event->withResponse(HttpFactory::getInstance()->createResponse(204));
            return;
        }
        if ($obj instanceof Action) {
            $xml = wx_make_xml_reply($obj, $self_id);
            $event->withResponse(HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/xml'], $xml));
        }
    } elseif ($ob->getDriver()->getName() === 'workerman') { // Workerman 使用异步模式，直接把回调存起来，然后等触发
        $event->setAsyncSend(); // 标记为异步发送
        $timer_id = $ob->getDriver()->getEventLoop()->addTimer(4500, function () use ($event, $self_id, $user_id) {
            $event->getAsyncSendCallable()(HttpFactory::getInstance()->createResponse(204));
            wx_global_unset($self_id . ':' . $user_id);
        });
        wx_global_set($self_id . ':' . $user_id, function (Action $action) use ($ob, $event, $timer_id, $self_id) {
            $xml = wx_make_xml_reply($action, $self_id);
            $event->getAsyncSendCallable()(HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/xml'], $xml));
            $ob->getDriver()->getEventLoop()->clearTimer($timer_id);
        });
        OneBot::getInstance()->dispatchEvent($msg_event);
    } else {
        $event->withResponse(HttpFactory::getInstance()->createResponse(500, 'Unknown Driver'));
    }
}, 0);

$ob->addActionHandler('send_message', function (Action $action) use ($ob) {
    Validator::validateParamsByAction($action, ['user_id' => true, 'message' => true]);
    Validator::validateMessageSegment($action->params['message']);
    if ($ob->getDriver()->getName() === 'swoole') {
        $channel_name = ($action->params['self_id'] ?? $ob->getSelfId()) . ':' . $action->params['user_id'];
        if (wx_global_isset($channel_name)) {
            $a = swoole_channel($channel_name)->push($action, 4.5);
            if ($a === false) {
                return ActionResponse::create($action->echo)->fail(34001, 'Wechat MP API cannot send message directly');
            }
            return ActionResponse::create($action->echo)->ok(['message_id' => message_id(), 'time' => time()]);
        }
        return ActionResponse::create($action->echo)->fail(34001, 'Wechat MP API cannot send message directly for now');
    }
    if ($ob->getDriver()->getName() === 'workerman') {
        $channel_name = ($action->params['self_id'] ?? $ob->getSelfId()) . ':' . $action->params['user_id'];
        if (wx_global_isset($channel_name)) {
            $a = wx_global_get($channel_name);
            if ($a instanceof Closure) {
                $a($action);
                return ActionResponse::create($action->echo)->ok(['message_id' => message_id(), 'time' => time()]);
            }
            return ActionResponse::create($action->echo)->fail(34001, 'Wechat MP API cannot send message directly for now');
        }
        return ActionResponse::create($action->echo)->fail(34001, 'Wechat MP API cannot send message directly for now');
    }
    return ActionResponse::create($action->echo)->fail(RetCode::INTERNAL_HANDLER_ERROR);
});

$ob->run();
