<?php

declare(strict_types=1);

use OneBot\Driver\Event\DriverInitEvent;
use OneBot\Driver\Event\EventProvider;
use OneBot\Util\Utils;
use OneBot\V12\Action\ActionResponse;
use OneBot\V12\Object\Action;
use OneBot\V12\Object\Event\Message\PrivateMessageEvent;
use OneBot\V12\OneBot;
use OneBot\V12\OneBotBuilder;
use OneBot\V12\Validator;

require_once __DIR__ . '/../vendor/autoload.php';

function message_id(): string
{
    return uniqid('', true);
}

$config = [
    'name' => 'repl',
    'platform' => 'qq',
    'self_id' => 'REPL-1',
    'db' => true,
    'logger' => [
        'class' => \OneBot\Logger\Console\ConsoleLogger::class,
        'level' => 'info',
    ],
    'driver' => [
        'class' => \OneBot\Driver\Swoole\SwooleDriver::class,
        'config' => [
            'init_in_user_process_block' => true,
        ],
    ],
    'communications' => [
        [
            'type' => 'http',
            'host' => '127.0.0.1',
            'port' => 2345,
            'worker_count' => 8,
            'access_token' => '',
            'event_enabled' => true,
            'event_buffer_size' => 100,
        ],
        [
            'type' => 'http_webhook',
            'url' => 'https://example.com/webhook',
            'access_token' => '',
            'timeout' => 5000,
        ],
        [
            'type' => 'websocket',
            'host' => '127.0.0.1',
            'port' => 2346,
            'access_token' => '',
        ],
        [
            'type' => 'ws_reverse',
            'url' => 'ws://127.0.0.1:9002',
            'access_token' => '',
            'reconnect_interval' => 1000,
        ],
    ],
];

const ONEBOT_APP_VERSION = '1.0.0-snapshot';

$ob = OneBotBuilder::buildFromArray($config); // 传入通信方式
$ob->addActionHandler('send_message', function (Action $obj) { // 写一个动作回调
    Validator::validateParamsByAction($obj, ['detail_type' => ['private']]); // 我这里只允许私聊动作，否则 BAD_PARAM
    ob_logger()->info(Utils::msgToString($obj->params['message'])); // 把字符串转换为终端输入，因为这是 REPL 的 demo
    return ActionResponse::create($obj->echo)->ok(['message_id' => message_id()]); // 返回消息回复
});

// 下面是一个简单的 REPL 实现，每次输入一行，就会触发一次 private.message 事件并通过设定的通信方式发送
EventProvider::addEventListener(DriverInitEvent::getName(), function (DriverInitEvent $event) {
    ob_logger()->info('Init 进程启动！' . $event->getDriver()->getName());
    $event->getDriver()->getEventLoop()->addReadEvent(STDIN, function ($x) use ($event) {
        $s = fgets($x);
        if ($s === false) {
            $event->getDriver()->getEventLoop()->delReadEvent($x);
            return;
        }
        $event = new PrivateMessageEvent('tty', trim($s));
        OneBot::getInstance()->dispatchEvent($event);
    });
}, 0);

$ob->run();
