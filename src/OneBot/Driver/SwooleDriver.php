<?php

/** @noinspection PhpPropertyOnlyWrittenInspection */

declare(strict_types=1);

namespace OneBot\Driver;

use OneBot\Driver\Event\DriverInitEvent;
use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\EventProvider;
use OneBot\Driver\Event\Process\UserProcessStartEvent;
use OneBot\Driver\Swoole\TopEventListener;
use OneBot\Driver\Swoole\UserProcess;
use Swoole\Event;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Timer;
use Swoole\WebSocket\Server as SwooleWebSocketServer;
use Throwable;

class SwooleDriver extends Driver
{
    /** @var SwooleHttpServer|SwooleWebSocketServer 服务端实例 */
    protected $server;

    /**
     * {@inheritDoc}
     */
    public function initInternalDriverClasses(?array $http, ?array $http_webhook, ?array $ws, ?array $ws_reverse)
    {
        if ($ws !== null) {
            $this->server = new SwooleWebSocketServer($ws['host'], $ws['port'], $this->getParam('swoole_server_mode', SWOOLE_PROCESS));
            $this->initServer();
            if ($http !== null) {
                ob_logger()->warning('检测到同时开启了http和正向ws，http的配置项将被忽略。');
                $this->initHttpServer();
            }
            $this->initWebSocketServer();
        } elseif ($http !== null) {
            // echo "新建http服务器.\n";
            $this->server = new SwooleHttpServer($http['host'], $http['port'], $this->getParam('swoole_server_mode', SWOOLE_PROCESS));
            $this->initHttpServer();
            $this->initServer();
        }
        if ($http_webhook !== null) {
            $this->http_webhook_config = $http_webhook;
        }
        if ($ws_reverse !== null) {
            $this->ws_reverse_config = $ws_reverse;
        }

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
                $process = new UserProcess(function () {
                    ProcessManager::initProcess(ONEBOT_PROCESS_USER, 0);
                    ob_logger()->debug('新建UserProcess');
                    try {
                        $event = new UserProcessStartEvent();
                        (new EventDispatcher())->dispatch($event);
                    } catch (Throwable $e) {
                        ExceptionHandler::getInstance()->handle($e);
                    }
                }, false);
                $this->server->addProcess($process);
            }
        }
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
            echo '启动！' . PHP_EOL;
            $this->server->start();
        } else {
            go(function () {
                EventDispatcher::dispatchWithHandler(new DriverInitEvent($this, self::SINGLE_PROCESS));
            });
            Event::wait();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addTimer(int $ms, callable $callable, int $times = 1, array $arguments = []): int
    {
        $timer_count = 0;
        return Timer::tick($ms, function ($timer_id, ...$params) use (&$timer_count, $callable, $times) {
            if ($times > 0) {
                ++$timer_count;
                if ($timer_count > $times) {
                    Timer::clear($timer_id);
                    return;
                }
            }
            $callable($timer_id, ...$params);
        }, ...$arguments);
    }

    /**
     * {@inheritDoc}
     */
    public function clearTimer(int $timer_id)
    {
        Timer::clear($timer_id);
    }

    /**
     * 初始化 Websocket 服务端，注册 WS 连接、收到消息和断开连接三种事件的顶层回调
     */
    private function initWebSocketServer(): void
    {
        $this->server->on('open', [TopEventListener::getInstance(), 'onOpen']);
        $this->server->on('message', [TopEventListener::getInstance(), 'onMessage']);
        $this->server->on('close', [TopEventListener::getInstance(), 'onClose']);
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
     */
    private function initHttpServer(): void
    {
        $this->server->on('request', [TopEventListener::getInstance(), 'onRequest']);
    }
}
