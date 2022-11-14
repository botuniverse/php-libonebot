<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use Exception;
use OneBot\Driver\Driver;
use OneBot\Driver\DriverEventLoopBase;
use OneBot\Driver\Event\DriverInitEvent;
use OneBot\Driver\Event\Process\UserProcessStartEvent;
use OneBot\Driver\Event\Process\WorkerStartEvent;
use OneBot\Driver\Interfaces\DriverInitPolicy;
use OneBot\Driver\Process\ProcessManager;
use OneBot\Driver\Socket\HttpClientSocketBase;
use OneBot\Driver\Workerman\Socket\HttpClientSocket;
use OneBot\Driver\Workerman\Socket\HttpServerSocket;
use OneBot\Driver\Workerman\Socket\WSClientSocket;
use OneBot\Driver\Workerman\Socket\WSServerSocket;
use OneBot\Exception\ExceptionHandler;
use OneBot\Http\Client\CurlClient;
use OneBot\Http\Client\StreamClient;
use OneBot\Util\Singleton;
use Throwable;

class WorkermanDriver extends Driver
{
    use Singleton;

    public const SUPPORTED_CLIENTS = [
        CurlClient::class,
        StreamClient::class,
    ];

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

    /**
     * 获取 WS Server Socket 对象，通过 Workerman 的 Worker 实例
     *
     * @param Worker $worker Workerman Worker 实例
     */
    public function getWSServerSocketByWorker(Worker $worker): ?WSServerSocket
    {
        foreach ($this->ws_socket as $v) {
            /** @var WSServerSocket $v */
            if ($v->worker->token === $worker->token) {
                return $v;
            }
        }
        return null;
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
                        $event = new DriverInitEvent($this);
                        ob_event_dispatcher()->dispatch($event);
                        break;
                    case DriverInitPolicy::MULTI_PROCESS_INIT_IN_USER_PROCESS:
                        ob_event_provider()->addEventListener(UserProcessStartEvent::getName(), function () {
                            $event = new DriverInitEvent($this);
                            ob_event_dispatcher()->dispatch($event);
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
                if (!empty(ob_event_provider()->getEventListeners(UserProcessStartEvent::getName()))) {
                    $process = Worker::$user_process = new UserProcess(function () use (&$process) {
                        ProcessManager::initProcess(ONEBOT_PROCESS_USER, 0);
                        ob_logger()->debug('新建UserProcess');
                        try {
                            $event = new UserProcessStartEvent($process);
                            ob_event_dispatcher()->dispatch($event);
                        } catch (Throwable $e) {
                            ExceptionHandler::getInstance()->handle($e);
                        }
                    });
                    Worker::$user_process->run();
                }
            }
            // 编写纯 WS Reverse 连接下的逻辑，就是不启动 Server 的
            if ($this->ws_socket === [] && $this->http_socket === []) {
                /** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */
                $worker = new Worker();
                Worker::$internal_running = true; // 加上这句就可以不需要必须输 start 命令才能启动了，直接启动
                $worker->onWorkerStart = [TopEventListener::getInstance(), 'onWorkerStart'];
                $worker->onWorkerStop = [TopEventListener::getInstance(), 'onWorkerStop'];
            }
            ob_logger()->debug('启动 Workerman 下的 Worker 们');
            Worker::runAll();
        } catch (Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
        }
    }

    public function getName(): string
    {
        return 'workerman';
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function initWSReverseClients(array $headers = [])
    {
        foreach ($this->ws_client_socket as $v) {
            $v->setClient(WebSocketClient::createFromAddress($v->getUrl(), array_merge($headers, $v->getHeaders())));
        }
    }

    public function getEventLoop(): DriverEventLoopBase
    {
        return EventLoop::getInstance();
    }

    public function createHttpClientSocket(array $config): HttpClientSocketBase
    {
        return new HttpClientSocket($config);
    }

    /**
     * 通过传入的配置文件初始化 Driver 下面的协议相关事件
     */
    protected function initInternalDriverClasses(?array $http, ?array $http_webhook, ?array $ws, ?array $ws_reverse): array
    {
        if (!empty($ws)) {
            // 因为 Workerman 要和 Swoole 统一，同时如果是 Linux，可以监听多个端口的情况下，先开一个 Worker，然后把剩下的塞进去。
            $ws_0 = array_shift($ws);
            $worker = new Worker('websocket://' . $ws_0['host'] . ':' . $ws_0['port']);
            $worker->count = $this->getParam('workerman_worker_num', 1);
            Worker::$internal_running = true;
            // ws server 相关事件
            $worker->onWebSocketConnect = fn (...$args) => TopEventListener::getInstance()->onWebSocketOpen($ws_0, ...$args);
            $worker->onClose = fn (...$args) => TopEventListener::getInstance()->onWebSocketClose($ws_0, ...$args);
            $worker->onMessage = fn (...$args) => TopEventListener::getInstance()->onWebSocketMessage($ws_0, ...$args);
            // worker 相关事件
            $worker->onWorkerStart = [TopEventListener::getInstance(), 'onWorkerStart'];
            $worker->onWorkerStop = [TopEventListener::getInstance(), 'onWorkerStop'];
            $worker->token = ob_uuidgen();
            $worker->flag = $ws_0['flag'] ?? 1;
            if (!empty($ws)) {
                ob_event_provider()->addEventListener(WorkerStartEvent::getName(), function () use ($ws) {
                    foreach ($ws as $ws_1) {
                        $worker = new Worker('websocket://' . $ws_1['host'] . ':' . $ws_1['port']);
                        $worker->reusePort = true;
                        $worker->token = ob_uuidgen();
                        $this->ws_socket[] = (new WSServerSocket($worker))->setFlag($ws_1['flag'] ?? 1);
                        // ws server 相关事件
                        $worker->onWebSocketConnect = fn (...$args) => TopEventListener::getInstance()->onWebSocketOpen($ws_1, ...$args);
                        $worker->onClose = fn (...$args) => TopEventListener::getInstance()->onWebSocketClose($ws_1, ...$args);
                        $worker->onMessage = fn (...$args) => TopEventListener::getInstance()->onWebSocketMessage($ws_1, ...$args);
                        $worker->listen();
                    }
                }, 999);
            }
            $this->ws_socket[] = (new WSServerSocket($worker))->setFlag($ws_0['flag'] ?? 1);

            if (!empty($http)) {
                $http_in_worker_start_init = true;
            }
        }
        if (!empty($http)) {
            if (isset($http_in_worker_start_init)) {
                $http_pending = $http;
            } else {
                $http_0 = array_shift($http);
                $http_pending = $http;
                ob_logger()->debug('在 Worker 中初始化 HTTP 服务器，端口：' . $http_0['port']);
                $worker = new Worker('http://' . $http_0['host'] . ':' . $http_0['port']);
                $worker->count = $this->getParam('workerman_worker_num', 1);
                Worker::$internal_running = true;
                // http server 相关事件
                $worker->onMessage = fn (...$args) => TopEventListener::getInstance()->onHttpRequest($http_0, ...$args);
                // worker 相关事件
                $worker->onWorkerStart = [TopEventListener::getInstance(), 'onWorkerStart'];
                $worker->onWorkerStop = [TopEventListener::getInstance(), 'onWorkerStop'];
                $worker->token = ob_uuidgen();
                $worker->flag = $http_0['flag'] ?? 1;
                $this->http_socket[] = new HttpServerSocket($worker, $http_0);
            }
            if (!empty($http_pending)) {
                ob_event_provider()->addEventListener(WorkerStartEvent::getName(), function () use ($http_pending) {
                    foreach ($http_pending as $http_1) {
                        $worker = new Worker('http://' . $http_1['host'] . ':' . $http_1['port']);
                        $worker->reusePort = true;
                        $worker->token = ob_uuidgen();
                        $worker->flag = $http_1['flag'] ?? 1;
                        $this->http_socket[] = new HttpServerSocket($worker, $http_1);
                        // http server 相关事件
                        $worker->onMessage = fn (...$args) => TopEventListener::getInstance()->onHttpRequest($http_1, ...$args);
                        $worker->listen();
                        ob_logger()->debug('在 Worker 中初始化 HTTP 服务器，端口：' . $http_1['port']);
                    }
                }, 998);
            }
        }
        /* @noinspection DuplicatedCode */
        foreach ($http_webhook as $v) {
            $this->http_client_socket[] = (new HttpClientSocket($v));
        }
        foreach ($ws_reverse as $v) {
            $this->ws_client_socket[] = (new WSClientSocket($v));
        }
        return [$this->http_socket !== [], $this->http_client_socket !== [], $this->ws_socket !== [], $this->ws_client_socket !== []];
    }
}
