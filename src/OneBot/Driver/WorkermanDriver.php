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
use OneBot\Driver\Event\WebSocket\WebSocketCloseEvent;
use OneBot\Driver\Event\WebSocket\WebSocketMessageEvent;
use OneBot\Driver\Event\WebSocket\WebSocketOpenEvent;
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

    /** @var Worker WS Worker */
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
            } elseif ($v['type'] == 'websocket') {
                $ws_index = $k;
            }
        }
        if ($ws_index !== null) {
            $this->ws_worker = new Worker('websocket://' . $comm[$ws_index]['host'] . ':' . $comm[$ws_index]['port']);
            $this->ws_worker->count = $comm[$ws_index]['worker_count'] ?? 4;
            Worker::$internal_running = true;  //不可以删除这句话哦
            $this->initWebSocketServer();
            $this->ws_worker->onWorkerStart = [$this, 'onWorkerStart'];
            $this->ws_worker->onWorkerStop = [$this, 'onWorkerStop'];
            if ($http_index !== null) {
                ob_logger()->warning('在 Workerman 驱动下不可以同时开启 http 和 websocket 模式，将优先开启 websocket');
            }
        } elseif ($http_index !== null) {
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
            if (ProcessManager::isSupportedMultiProcess()) {
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
                            if ($this->getParam('init_in_user_process_block', true) === true) {
                                while (true) {
                                    sleep(100000);
                                }
                            }
                        }, 1);
                        break;
                }
                // 添加插入用户进程的启动仪式
                if (!empty(EventProvider::getEventListeners(UserProcessStartEvent::getName()))) {
                    Worker::$user_process = new UserProcess(function () {
                        ProcessManager::initProcess(ONEBOT_PROCESS_USER, 0);
                        ob_logger()->debug('新建UserProcess');
                        try {
                            $event = new UserProcessStartEvent();
                            (new EventDispatcher())->dispatch($event);
                        } catch (Throwable $e) {
                            ExceptionHandler::getInstance()->handle($e);
                        }
                    });
                    Worker::$user_process->run();
                }
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
        // WebSocket 隐藏特性： _SERVER 全局变量会在 onWebSocketConnect 中被替换为当前连接的 Header 相关信息
        $this->ws_worker->onWebSocketConnect = function (TcpConnection $connection, $data) {
            try {
                global $_SERVER;
                $headers = $this->convertHeaderFromGlobal($_SERVER);
                $server_request = HttpFactory::getInstance()->createServerRequest(
                    $_SERVER['REQUEST_METHOD'],
                    'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                    $headers
                );
                $event = new WebSocketOpenEvent($server_request, $connection->id);
                (new EventDispatcher())->dispatch($event);
                if (is_object($event->getResponse()) && method_exists($event->getResponse(), '__toString')) {
                    $connection->close((string) $event->getResponse());
                }
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
            }
        };
        $this->ws_worker->onClose = function (TcpConnection $connection) {
            $event = new WebSocketCloseEvent($connection->id);
            (new EventDispatcher())->dispatch($event);
        };
        $this->ws_worker->onMessage = function (TcpConnection $connection, $data) {
            try {
                ob_logger()->debug('WebSocket message from: ' . $connection->id);
                $event = new WebSocketMessageEvent($connection->id, $data, function (int $fd, string $data) use ($connection) {
                    return $connection->send($data);
                });
                (new EventDispatcher())->dispatch($event);
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
            }
        };
    }

    /**
     * 将 $_SERVER 变量中的 Header 提取出来转换为数组 K-V 形式
     */
    private function convertHeaderFromGlobal(array $server): array
    {
        $headers = [];
        foreach ($server as $header => $value) {
            $header = strtolower($header);
            if (strpos($header, 'http_') === 0) {
                $string = '_' . str_replace('_', ' ', strtolower($header));
                $header = ltrim(str_replace(' ', '-', ucwords($string)), '_');
                $header = substr($header, 5);
                $headers[$header] = $value;
            }
        }
        return $headers;
    }
}
