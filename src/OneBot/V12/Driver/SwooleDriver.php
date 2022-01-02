<?php

declare(strict_types=1);

namespace OneBot\V12\Driver;

use Error;
use MessagePack\MessagePack;
use OneBot\V12\Action\ActionResponse;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\MPUtils;
use OneBot\V12\Object\Event\OneBotEvent;
use OneBot\V12\RetCode;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Server;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as SwooleWebSocketServer;
use Throwable;

class SwooleDriver extends Driver
{
    /**
     * @var SwooleHttpServer|SwooleWebSocketServer
     */
    private $server;

    public function __construct()
    {
    }

    public function emitOBEvent(OneBotEvent $event): bool
    {
        return false;
    }

    public function initComm()
    {
        $enabled_com = $this->config->getEnabledCommunications();
        $has_ws = false;
        $has_http = false;
        foreach ($enabled_com as $k => $v) {
            if ($v['type'] == 'ws') {
                $has_ws = $k;
            }
            if ($v['type'] == 'http') {
                $has_http = $k;
            }
        }
        if ($has_ws !== false) {
            $this->server = new SwooleWebSocketServer($enabled_com[$has_ws]['host'], $enabled_com[$has_ws]['port']);
            $this->initServer();
            if ($has_http !== false) {
                ob_logger()->warning('检测到同时开启了http和正向ws，http的配置项将被忽略。');
                $this->initHttpServer();
            }
            $this->initWebSocketServer();
        } elseif ($has_http !== false) {
            //echo "新建http服务器.\n";
            $this->server = new SwooleHttpServer($enabled_com[$has_http]['host'], $enabled_com[$has_http]['port']);
            $this->initHttpServer();
        } else {
            go(function () {
                //TODO: 在协程状态下启动纯客户端模式
            });
        }
    }

    public function run()
    {
        if ($this->server !== null) {
            $this->server->start();
        }
    }

    public function onRequestEvent(Request $request, Response $response)
    {
        try {
            if (($request->header['content-type'] ?? null) === 'application/json') {
                $response_obj = $this->emitHttpRequest($request->rawContent());
                $response->setHeader('content-type', 'application/json');
                $response->end(json_encode($response_obj, JSON_UNESCAPED_UNICODE));
            } elseif (($request->header['content-type'] ?? null) === 'application/msgpack') {
                $response_obj = $this->emitHttpRequest($request->rawContent());
                $response->setHeader('content-type', 'application/msgpack');
                $response->end(MessagePack::pack($response_obj));
            } else {
                throw new OneBotFailureException(RetCode::BAD_REQUEST);
            }
        } catch (OneBotFailureException $e) {
            $response_obj = ActionResponse::create($e->getActionObject()->echo ?? null)->fail($e->getRetCode());
            $response->setHeader('content-type', 'application/json');
            $response->end(json_encode($response_obj, JSON_UNESCAPED_UNICODE));
            ob_logger()->warning('OneBot Failure: ' . RetCode::getMessage($e->getRetCode()) . '(' . $e->getRetCode() . ') at ' . $e->getFile() . ':' . $e->getLine());
        } catch (Throwable | Error $e) {
            $response_obj = ActionResponse::create($action_obj->echo ?? null)->fail(RetCode::INTERNAL_HANDLER_ERROR);
            $response->setHeader('content-type', 'application/json');
            $response->end(json_encode($response_obj, JSON_UNESCAPED_UNICODE));
            ob_logger()->error('Unhandled ' . get_class($e) . ': ' . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString());
        }
    }

    /**
     * 初始化使用ws通信方式的注册事件.
     */
    private function initWebSocketServer()
    {
        $this->server->on('open', [$this, 'onOpenEvent']);
        $this->server->on('message', [$this, 'onMessageEvent']);
        $this->server->on('close', [$this, 'onCloseEvent']);
    }

    private function initServer()
    {
        $this->server->set([
            'max_coroutine' => 300000,
            'max_wait_time' => 5,
        ]);
        $this->server->on('workerstart', function (Server $server) {
            echo '已启动服务器 at ' . $server->host . ':' . $server->port;
            MPUtils::initProcess(ONEBOT_PROCESS_WORKER, $server->worker_id);
        });
    }

    /**
     * 初始化使用http通信方式的注册事件.
     */
    private function initHttpServer()
    {
        $this->server->on('request', [$this, 'onRequestEvent']);
    }

    private function onOpenEvent(?SwooleWebSocketServer $server, Request $request)
    {
        //TODO: 编写swoole收到正向ws连接请求的流程
    }

    private function onMessageEvent(?SwooleWebSocketServer $server, Frame $frame)
    {
        //TODO: 编写swoole收到websocket包的流程
    }

    private function onCloseEvent(?Server $server, $fd)
    {
        //TODO: 编写swoole断开ws连接请求的流程
    }
}
