<?php

/** @noinspection PhpPropertyOnlyWrittenInspection */

declare(strict_types=1);

namespace OneBot\Driver\Swoole;

use Exception;
use OneBot\Driver\Driver;
use OneBot\Driver\DriverEventLoopBase;
use OneBot\Driver\Event\DriverInitEvent;
use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\EventProvider;
use OneBot\Driver\Event\Process\UserProcessStartEvent;
use OneBot\Driver\ExceptionHandler;
use OneBot\Driver\Interfaces\DriverInitPolicy;
use OneBot\Driver\Process\ProcessManager;
use OneBot\Driver\Swoole\Socket\HttpClientSocket;
use OneBot\Driver\Swoole\Socket\HttpServerSocket;
use OneBot\Driver\Swoole\Socket\WSClientSocket;
use OneBot\Driver\Swoole\Socket\WSServerSocket;
use OneBot\Http\Client\Exception\ClientException;
use OneBot\Http\Client\SwooleClient;
use OneBot\Util\Singleton;
use Swoole\Event;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Server;
use Swoole\Server\Port;
use Swoole\WebSocket\Server as SwooleWebSocketServer;
use Throwable;

class SwooleDriver extends Driver
{
    use Singleton;

    public const SUPPORTED_CLIENTS = [
        SwooleClient::class,
    ];

    /** @var SwooleHttpServer|SwooleWebSocketServer 服务端实例 */
    protected $server;

    /**
     * @throws Exception
     */
    public function __construct(array $params = [])
    {
        if (static::$instance !== null) {
            throw new Exception('不能重复初始化');
        }
        static::$instance = $this;
        parent::__construct($params);
    }

    public function getEventLoop(): DriverEventLoopBase
    {
        return EventLoop::getInstance();
    }

    /**
     * {@inheritDoc}
     */
    public function initInternalDriverClasses(?array $http, ?array $http_webhook, ?array $ws, ?array $ws_reverse): array
    {
        if (!empty($ws)) {
            $ws_0 = array_shift($ws);
            $this->server = new SwooleWebSocketServer($ws_0['host'], $ws_0['port'], $this->getParam('swoole_server_mode', SWOOLE_PROCESS));
            $this->initServer();
            $this->initWebSocketServer($this->server, $ws_0['flag'] ?? 1);
            $this->ws_socket[] = (new WSServerSocket($this->server, $ws_0))->setFlag($ws_0['flag'] ?? 1);
            if (!empty($ws)) {
                foreach ($ws as $v) {
                    $this->addWSServerListener($v);
                }
            }
            if (!empty($http)) {
                foreach ($http as $v) {
                    $this->addHttpServerListener($v);
                }
            }
        } elseif (!empty($http)) {
            $http_0 = array_shift($http);
            $this->server = new SwooleHttpServer($http_0['host'], $http_0['port'], $this->getParam('swoole_server_mode', SWOOLE_PROCESS));
            $this->initServer();
            $this->initHttpServer($this->server, $http_0['flag'] ?? 1);
            $this->http_socket[] = (new HttpServerSocket($this->server, $http_0))->setFlag($http_0['flag'] ?? 1);
            if (!empty($http)) {
                foreach ($http as $v) {
                    $this->addHttpServerListener($v);
                }
            }
        }
        /* @noinspection DuplicatedCode */
        foreach ($http_webhook as $v) {
            $this->http_client_socket[] = (new HttpClientSocket($v['url'], $v['header'] ?? [], $v['access_token'] ?? '', $v['timeout'] ?? 5))->setFlag($v['flag'] ?? 1);
        }
        foreach ($ws_reverse as $v) {
            $this->ws_client_socket[] = (new WSClientSocket($v['url'], $v['header'] ?? [], $v['access_token'] ?? '', $v['reconnect_interval'] ?? 5))->setFlag($v['flag'] ?? 1);
        }
        return [$this->http_socket !== [], $this->http_client_socket !== [], $this->ws_socket !== [], $this->ws_client_socket !== []];
    }

    /**
     * 给我跑！人和代码有一个能跑就行！
     *
     * 函数分为两部分，对于 Swoole 驱动而言，如果通信方式有需要启动 Server 的（HTTP/WebSocket），则需要启动 Server 模式。
     *
     * 反之，直接使用 SINGLE_PROCESS 模式在协程中启动，然后初始化 HTTP Webhook 或 WS Reverse 的连接，然后接着初始化用户自定的协议。
     */
    public function run(): void
    {
        if ($this->server !== null) {
            switch ($this->getDriverInitPolicy()) {
                case DriverInitPolicy::MULTI_PROCESS_INIT_IN_MASTER:
                    $event = new DriverInitEvent($this);
                    (new EventDispatcher())->dispatch($event);
                    break;
                case DriverInitPolicy::MULTI_PROCESS_INIT_IN_USER_PROCESS:
                    EventProvider::addEventListener(UserProcessStartEvent::getName(), function () {
                        $event = new DriverInitEvent($this);
                        (new EventDispatcher())->dispatch($event);
                        if ($this->getParam('init_in_user_process_block', true) === true) {
                            /* @phpstan-ignore-next-line */
                            while (true) {
                                sleep(100000);
                            }
                        }
                    }, 1);
                    break;
            }
            // 添加插入用户进程的启动仪式
            if (!empty(EventProvider::getEventListeners(UserProcessStartEvent::getName()))) {
                $process = new UserProcess(function () use (&$process) {
                    ProcessManager::initProcess(ONEBOT_PROCESS_USER, 0);
                    ob_logger()->debug('新建UserProcess');
                    try {
                        $event = new UserProcessStartEvent($process);
                        (new EventDispatcher())->dispatch($event);
                    } catch (Throwable $e) {
                        ExceptionHandler::getInstance()->handle($e);
                    }
                }, false);
                $this->server->addProcess($process);
            }
            $this->server->start();
        } else {
            go(function () {
                EventDispatcher::dispatchWithHandler(new DriverInitEvent($this, self::SINGLE_PROCESS));
            });
            Event::wait();
        }
    }

    public function getName(): string
    {
        return 'swoole';
    }

    /**
     * @throws ClientException
     */
    public function initWSReverseClients(array $headers = [])
    {
        foreach ($this->ws_client_socket as $v) {
            $v->setClient(WebSocketClient::createFromAddress($v->getUrl(), array_merge($headers, $v->getHeaders()), $this->getParam('swoole_ws_client_set', ['websocket_mask' => true])));
        }
    }

    /**
     * 初始化 Websocket 服务端，注册 WS 连接、收到消息和断开连接三种事件的顶层回调
     *
     * @param Port|Server $obj  Server 或 Port 对象
     * @param int         $flag 类型标记
     */
    private function initWebSocketServer($obj, int $flag): void
    {
        $obj->on('open', function (...$params) use ($flag) {
            TopEventListener::getInstance()->onOpen($flag, ...$params);
        });
        $obj->on('message', function (...$params) use ($flag) {
            TopEventListener::getInstance()->onMessage($flag, ...$params);
        });
        $obj->on('close', function (...$params) use ($flag) {
            TopEventListener::getInstance()->onClose($flag, ...$params);
        });
    }

    /**
     * 初始化服务端，注册 Worker、Manager 进程的启动和停止事件的顶层回调，以及 Swoole Server 一些核心的配置项
     */
    private function initServer(): void
    {
        $this->server->set($this->getParam('swoole_set', [
            'max_coroutine' => 300000, // 默认如果不手动设置 Swoole 的话，提供的协程数量尽量多一些，保证并发性能（反正协程不要钱）
            'max_wait_time' => 5,      // 安全 shutdown 时候，让 Swoole 等待 Worker 进程响应的最大时间
        ]));
        $this->server->on('workerstart', [TopEventListener::getInstance(), 'onWorkerStart']);
        $this->server->on('managerstart', [TopEventListener::getInstance(), 'onManagerStart']);
        $this->server->on('managerstop', [TopEventListener::getInstance(), 'onManagerStop']);
        $this->server->on('workerstop', [TopEventListener::getInstance(), 'onWorkerStop']);
    }

    /**
     * 初始化使用http通信方式的注册事件，包含收到 HTTP 请求的事件顶层回调
     * @param Port|SwooleHttpServer $obj  Server 或 Port 对象
     * @param int                   $flag Socket 类型标记
     */
    private function initHttpServer($obj, int $flag): void
    {
        $obj->on('request', function (...$params) use ($flag) {
            TopEventListener::getInstance()->onRequest($flag, ...$params);
        });
    }

    private function addWSServerListener($v)
    {
        /** @var Port $port */
        $port = $this->server->addlistener($v['host'], $v['port'], SWOOLE_SOCK_TCP);
        $port->set([
            'open_websocket_protocol' => true,
            'open_http_protocol' => false,
        ]);
        $this->initWebSocketServer($port, $v['flag'] ?? 1);
        $this->ws_socket[] = new WSServerSocket($port, $v);
    }

    private function addHttpServerListener($v)
    {
        $port = $this->server->addlistener($v['host'], $v['port'], SWOOLE_SOCK_TCP);
        $port->set([
            'open_websocket_protocol' => false,
            'open_http_protocol' => true,
        ]);
        $this->initHttpServer($port, $v['flag'] ?? 1);
        $this->http_socket[] = new HttpServerSocket($port, $v);
    }
}
