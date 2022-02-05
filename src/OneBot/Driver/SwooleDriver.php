<?php

declare(strict_types=1);

namespace OneBot\Driver;

use OneBot\Driver\Event\Event;
use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\HttpRequestEvent;
use OneBot\Driver\Event\WebSocketCloseEvent;
use OneBot\Driver\Event\WebSocketMessageEvent;
use OneBot\Driver\Event\WebSocketOpenEvent;
use OneBot\Driver\Event\WorkerStartEvent;
use OneBot\Http\HttpFactory;
use OneBot\Logger\Console\ExceptionHandler;
use OneBot\Util\MPUtils;
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
    protected $server;

    public function initDriverProtocols(array $comm): void
    {
        $ws_index = null;
        $http_index = null;
        foreach ($comm as $k => $v) {
            if ($v['type'] === 'websocket') {
                $ws_index = $k;
            }
            if ($v['type'] === 'http') {
                $http_index = $k;
            }
        }
        if ($ws_index !== null) {
            $this->server = new SwooleWebSocketServer($comm[$ws_index]['host'], $comm[$ws_index]['port']);
            $this->initServer();
            if ($http_index !== null) {
                ob_logger()->warning('检测到同时开启了http和正向ws，http的配置项将被忽略。');
                $this->initHttpServer();
            }
            $this->initWebSocketServer();
        } elseif ($http_index !== null) {
            //echo "新建http服务器.\n";
            $this->server = new SwooleHttpServer($comm[$http_index]['host'], $comm[$http_index]['port']);
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

    /**
     * 初始化使用ws通信方式的注册事件.
     */
    private function initWebSocketServer()
    {
        $this->server->on('open', function (SwooleWebSocketServer $server, Request $request) {
            ob_logger()->debug('WebSocket connection open: ' . $request->fd);
            if (empty($content = $request->rawContent())) {
                $content = null;
            }
            $event = new WebSocketOpenEvent(HttpFactory::getInstance()->createServerRequest(
                $request->server['request_method'],
                $request->server['request_uri'],
                $request->header,
                $content
            ));
            try {
                (new EventDispatcher(Event::EVENT_WEBSOCKET_OPEN))->dispatch($event);
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
            }
        });

        $this->server->on('message', function (?SwooleWebSocketServer $server, Frame $frame) {
            try {
                ob_logger()->debug('WebSocket message from: ' . $frame->fd);
                $event = new WebSocketMessageEvent($frame->fd, $frame->data, function (int $fd, string $data) use ($server) {
                    return $server->push($fd, $data);
                });
                $event->setOriginFrame($frame);
                (new EventDispatcher(Event::EVENT_WEBSOCKET_MESSAGE))->dispatch($event);
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
            }
        });
        $this->server->on('close', function (?Server $server, $fd) {
            try {
                ob_logger()->debug('WebSocket closed from: ' . $fd);
                $event = new WebSocketCloseEvent($fd);
                (new EventDispatcher(Event::EVENT_WEBSOCKET_CLOSE))->dispatch($event);
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
            }
        });
    }

    private function initServer()
    {
        $this->server->set([
            'max_coroutine' => 300000,
            'max_wait_time' => 5,
        ]);
        $this->server->on('workerstart', function (Server $server) {
            MPUtils::initProcess(ONEBOT_PROCESS_WORKER, $server->worker_id);
            try {
                $event = new WorkerStartEvent();
                (new EventDispatcher(Event::EVENT_WORKER_START))->dispatch($event);
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
            }
        });
    }

    /**
     * 初始化使用http通信方式的注册事件.
     */
    private function initHttpServer()
    {
        $this->server->on('request', function (Request $request, Response $response) {
            ob_logger()->debug('Http request: ' . $request->server['request_uri']);
            if (empty($content = $request->rawContent())) {
                $content = null;
            }
            $event = new HttpRequestEvent(HttpFactory::getInstance()->createServerRequest(
                $request->server['request_method'],
                $request->server['request_uri'],
                $request->header,
                $content
            ));
            try {
                (new EventDispatcher(Event::EVENT_HTTP_REQUEST))->dispatch($event);
                if ($event->getResponse() !== null) {
                    $psr_response = $event->getResponse();
                    foreach ($psr_response->getHeaders() as $k => $v) {
                        if (is_array($v)) {
                            $response->setHeader($k, implode(';', $v));
                        }
                    }
                    $response->setStatusCode($psr_response->getStatusCode());
                    $response->end($psr_response->getBody());
                } else {
                    $response->setStatusCode(204);
                    $response->end();
                }
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
                $response->status(500);
                $response->end('Internal Server Error');
            }
        });
    }
}
