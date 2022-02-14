<?php

declare(strict_types=1);

namespace OneBot\Driver;

use OneBot\Driver\Event\DriverInitEvent;
use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\EventProvider;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Event\Process\UserProcessStartEvent;
use OneBot\Driver\Event\Process\WorkerStartEvent;
use OneBot\Driver\Event\Process\WorkerStopEvent;
use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Driver\Workerman\UserProcess;
use OneBot\Driver\Workerman\Worker;
use OneBot\Http\HttpFactory;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;

class WorkermanDriver extends Driver
{
    /** @var Worker HTTP Worker */
    protected $http_worker;

    protected $ws_worker;

    public function onWorkerStart(Worker $worker)
    {
        ProcessManager::initProcess(ONEBOT_PROCESS_WORKER, $worker->id);
        try {
            switch ($this->getDriverInitPolicy()) {
                case DriverInitPolicy::MULTI_PROCESS_INIT_IN_ALL_PROCESSES:
                case DriverInitPolicy::MULTI_PROCESS_INIT_IN_ALL_WORKERS:
                    $event = new DriverInitEvent($this);
                    (new EventDispatcher())->dispatch($event);
                    break;
                case DriverInitPolicy::MULTI_PROCESS_INIT_IN_FIRST_WORKER:
                    if (ProcessManager::getProcessId() === 0) {
                        $event = new DriverInitEvent($this);
                        (new EventDispatcher())->dispatch($event);
                    }
                    break;
            }
            $event = new WorkerStartEvent();
            ob_logger()->debug('新建Worker' . $worker->id);
            (new EventDispatcher())->dispatch($event);
        } catch (Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
        }
    }

    public function onWorkerStop()
    {
        try {
            $event = new WorkerStopEvent();
            (new EventDispatcher())->dispatch($event);
        } catch (Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
        }
    }

    /**
     * 通过传入的配置文件初始化 Driver 下面的协议相关事件
     */
    public function initDriverProtocols(array $comm): void
    {
        $http_index = null;
        $ws_index = null;
        foreach ($comm as $k => $v) {
            if ($v['type'] === 'http') {
                $http_index = $k;
            } elseif ($v['type'] == 'ws') {
                $ws_index = $k;
            }
        }
        if ($ws_index !== null) {
            $this->ws_worker = new Worker('websocket://' . $comm[$ws_index]['host'] . ':' . $comm[$ws_index]['port']);
            $this->ws_worker->count = $comm[$ws_index]['worker_count'] ?? 4;
            Worker::$internal_running = true;
            $this->initWebSocketServer();
            $this->ws_worker->onWorkerStart = [$this, 'onWorkerStart'];
            $this->ws_worker->onWorkerStop = [$this, 'onWorkerStop'];
        }
        if ($http_index !== null) {
            // 定义 Workerman 的 worker 和相关回调
            $this->http_worker = new Worker('http://' . $comm[$http_index]['host'] . ':' . $comm[$http_index]['port']);
            $this->http_worker->count = $comm[$http_index]['worker_count'] ?? 4;
            Worker::$internal_running = true; // 加上这句就可以不需要必须输 start 命令才能启动了，直接启动
            $this->initHttpServer();
            $this->http_worker->onWorkerStart = [$this, 'onWorkerStart'];
            $this->http_worker->onWorkerStop = [$this, 'onWorkerStop'];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function run(): void
    {
        try {
            // 如果是 POSIX 环境并且初始化策略为MASTER进程下或者初始化策略为ALL，则调用初始化方法
            if (
                ProcessManager::isSupportedMultiProcess()
            ) {
                switch ($this->getDriverInitPolicy()) {
                    case DriverInitPolicy::MULTI_PROCESS_INIT_IN_MASTER:
                    case DriverInitPolicy::MULTI_PROCESS_INIT_IN_MANAGER:
                    case DriverInitPolicy::MULTI_PROCESS_INIT_IN_ALL_PROCESSES:
                        $event = new DriverInitEvent($this);
                        (new EventDispatcher())->dispatch($event);
                        break;
                    case DriverInitPolicy::MULTI_PROCESS_INIT_IN_USER_PROCESS:
                        EventProvider::addEventListener(UserProcessStartEvent::getName(), function () {
                            $event = new DriverInitEvent($this);
                            (new EventDispatcher())->dispatch($event);
                        }, 1);
                        break;
                }
            }
            // 添加插入用户进程的启动仪式
            if (!empty(EventProvider::getEventListeners(UserProcessStartEvent::getName()))) {
                Worker::$user_process = new UserProcess(function () {
                    ob_logger()->debug('新建UserProcess');
                    try {
                        $event = new UserProcessStartEvent();
                        (new EventDispatcher())->dispatch($event);
                    } catch (Throwable $e) {
                        ExceptionHandler::getInstance()->handle($e);
                    }
                });
                Worker::$user_process->run();
                Worker::$user_process_pid = Worker::$user_process->getPid();
            }
            // 启动 Workerman 下的 Worker 们
            Worker::runAll();
        } catch (Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
        }
    }

    public function getHttpWebhookUrl(): string
    {
        // TODO: Implement getHttpWebhookUrl() method.
        return '';
    }

    public function getWSReverseClient(): ?WebSocketClientInterface
    {
        // TODO: Implement getWSReverseClient() method.
        return null;
    }

    /**
     * 初始化 HTTP Request 响应的回调
     */
    private function initHttpServer()
    {
        $this->http_worker->onMessage = static function (TcpConnection $connection, Request $request) {
            ob_logger()->debug('Http request: ' . $request->uri());
            $event = new HttpRequestEvent(HttpFactory::getInstance()->createServerRequest(
                $request->method(),
                $request->uri(),
                $request->header(),
                $request->rawBody()
            ));
            $response = new WorkermanResponse();
            try {
                (new EventDispatcher())->dispatch($event);
                if (($psr_response = $event->getResponse()) !== null) {
                    $response->withStatus($psr_response->getStatusCode());
                    $response->withHeaders($psr_response->getHeaders());
                    $response->withBody($psr_response->getBody());
                } else {
                    $response->withStatus(204);
                }
                $connection->send($response);
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
                $response->withStatus(500);
                $response->withBody('Internal Server Error');
                $connection->send($response);
            }
        };
    }

    private function initWebSocketServer()
    {
        $this->ws_worker->onMessage = function (TcpConnection $connection, $data) {
            ob_dump($data);
        };
    }
}
