<?php

/** @noinspection PhpPropertyOnlyWrittenInspection */

declare(strict_types=1);

namespace OneBot\Driver;

use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Event\Process\WorkerStartEvent;
use OneBot\Driver\Event\WebSocket\WebSocketCloseEvent;
use OneBot\Driver\Event\WebSocket\WebSocketMessageEvent;
use OneBot\Driver\Event\WebSocket\WebSocketOpenEvent;
use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Http\HttpFactory;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Process;
use Swoole\Server;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as SwooleWebSocketServer;
use Throwable;

class SwooleDriver extends Driver
{
    /** @var SwooleHttpServer|SwooleWebSocketServer 服务端实例 */
    protected $server;

    /**
     * @var WebSocketClientInterface
     */
    private $ws_reverse_client;

    /**
     * @var string
     */
    private $http_webhook_url;

    public function initDriverProtocols(array $comm): void
    {
        $ws_index = null;
        $http_index = null;
        $has_http_webhook = null;
        $has_ws_reverse = null;
        foreach ($comm as $k => $v) {
            switch ($v['type']) {
                case 'websocket':
                    $ws_index = $k;
                    break;
                case 'http':
                    $http_index = $k;
                    break;
                case 'http_webhook':
                    $has_http_webhook = $k;
                    break;
                case 'ws_reverse':
                    $has_ws_reverse = $k;
                    break;
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
        }
        if ($has_http_webhook !== null) {
            $this->http_webhook_url = $comm[$has_http_webhook]['url'];
        }

        $process = new Process(function () {
            //while (true) {
            /* @noinspection PhpComposerExtensionStubsInspection */
            echo date('H:i:s') . ' 开启个人进程[' . posix_getpid() . ']！' . $this->server->worker_id . PHP_EOL;

            sleep(2);
        //}
        }, false);
        echo '牛逼' . PHP_EOL;
        $this->server->addProcess($process);
    }

    /**
     * {@inheritDoc}
     */
    public function run(): void
    {
        if ($this->ws_reverse_client !== null) {
            $this->ws_reverse_client->connect();
        }
        if ($this->server !== null) {
            echo '启动！' . PHP_EOL;
            $this->server->start();
        } else {
            \Swoole\Event::wait();
        }
    }

    public function getHttpWebhookUrl(): string
    {
        return $this->http_webhook_url;
    }

    /**
     * @return WebSocketClientInterface
     */
    public function getWSReverseClient(): ?WebSocketClientInterface
    {
        return $this->ws_reverse_client;
    }

    /**
     * 初始化 Websocket 服务端
     */
    private function initWebSocketServer(): void
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
            ), $request->fd);
            try {
                (new EventDispatcher())->dispatch($event);
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
                (new EventDispatcher())->dispatch($event);
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
            }
        });

        $this->server->on('close', function (?Server $server, $fd) {
            try {
                ob_logger()->debug('WebSocket closed from: ' . $fd);
                $event = new WebSocketCloseEvent($fd);
                (new EventDispatcher())->dispatch($event);
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
            }
        });
    }

    /**
     * 初始化服务端
     */
    private function initServer(): void
    {
        $this->server->set([
            'max_coroutine' => 300000,
            'max_wait_time' => 5,
        ]);
        $this->server->on('workerstart', function (Server $server) {
            ProcessManager::initProcess(ONEBOT_PROCESS_WORKER, $server->worker_id);
            try {
                $event = new WorkerStartEvent();
                (new EventDispatcher())->dispatch($event);
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
            }
        });
    }

    /**
     * 初始化使用http通信方式的注册事件.
     */
    private function initHttpServer(): void
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
                (new EventDispatcher())->dispatch($event);
                if (($psr_response = $event->getResponse()) !== null) {
                    foreach ($psr_response->getHeaders() as $header => $value) {
                        if (is_array($value)) {
                            $response->setHeader($header, implode(';', $value));
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
