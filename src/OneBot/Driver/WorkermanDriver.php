<?php

declare(strict_types=1);

namespace OneBot\Driver;

use Exception;
use OneBot\Driver\Event\DriverInitEvent;
use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\EventProvider;
use OneBot\Driver\Event\Process\UserProcessStartEvent;
use OneBot\Driver\Event\Process\WorkerStartEvent;
use OneBot\Driver\Workerman\Socket\HttpServerSocket;
use OneBot\Driver\Workerman\Socket\HttpWebhookSocket;
use OneBot\Driver\Workerman\Socket\WSReverseSocket;
use OneBot\Driver\Workerman\Socket\WSServerSocket;
use OneBot\Driver\Workerman\TopEventListener;
use OneBot\Driver\Workerman\UserProcess;
use OneBot\Driver\Workerman\WebSocketClient;
use OneBot\Driver\Workerman\Worker;
use OneBot\Http\Client\CurlClient;
use OneBot\Http\Client\StreamClient;
use OneBot\Util\Singleton;
use Throwable;
use Workerman\Events\EventInterface;
use Workerman\Timer;

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
                    $process = Worker::$user_process = new UserProcess(function () use (&$process) {
                        ProcessManager::initProcess(ONEBOT_PROCESS_USER, 0);
                        ob_logger()->debug('新建UserProcess');
                        try {
                            $event = new UserProcessStartEvent($process);
                            (new EventDispatcher())->dispatch($event);
                        } catch (Throwable $e) {
                            ExceptionHandler::getInstance()->handle($e);
                        }
                    });
                    Worker::$user_process->run();
                }
            }
            // TODO: 编写纯 WS Reverse 连接下的逻辑，就是不启动 Server 的
            if ($this->ws_socket === [] && $this->http_socket === []) {
                $worker = new Worker();
                Worker::$internal_running = true; // 加上这句就可以不需要必须输 start 命令才能启动了，直接启动
                $worker->onWorkerStart = [TopEventListener::getInstance(), 'onWorkerStart'];
                $worker->onWorkerStop = [TopEventListener::getInstance(), 'onWorkerStop'];
            }
            // 启动 Workerman 下的 Worker 们
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
     */
    public function addTimer(int $ms, callable $callable, int $times = 1, array $arguments = []): int
    {
        $timer_count = 0;
        return Timer::add($ms / 1000, function () use (&$timer_id, &$timer_count, $callable, $times, $arguments) {
            if ($times > 0) {
                ++$timer_count;
                if ($timer_count > $times) {
                    Timer::del($timer_id);
                    return;
                }
            }
            $callable($timer_id, ...$arguments);
        }, $arguments);
    }

    /**
     * {@inheritDoc}
     */
    public function clearTimer(int $timer_id)
    {
        Timer::del($timer_id);
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function initWSReverseClients(array $headers = [])
    {
        foreach ($this->ws_reverse_socket as $v) {
            $v->setClient(WebSocketClient::createFromAddress($v->getUrl(), array_merge($headers, $v->getHeaders())));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addReadEvent($fd, callable $callable)
    {
        Worker::getEventLoop()->add($fd, EventInterface::EV_READ, $callable);
    }

    public function delReadEvent($fd)
    {
        Worker::getEventLoop()->del($fd, EventInterface::EV_READ);
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
            $worker->onWebSocketConnect = [TopEventListener::getInstance(), 'onWebSocketOpen'];
            $worker->onClose = [TopEventListener::getInstance(), 'onWebSocketClose'];
            $worker->onMessage = [TopEventListener::getInstance(), 'onWebSocketMessage'];
            // worker 相关事件
            $worker->onWorkerStart = [TopEventListener::getInstance(), 'onWorkerStart'];
            $worker->onWorkerStop = [TopEventListener::getInstance(), 'onWorkerStop'];
            $worker->token = ob_uuidgen();
            $worker->flag = $ws_0['flag'] ?? 1;
            // 将剩下的 ws 协议配置加入到 worker 中
            if (DIRECTORY_SEPARATOR === '\\') {
                ob_logger()->warning('Workerman 在 Windows 下只支持一个 Worker。');
                // Windows 下，Workerman 只支持一个 Worker，所以需要把剩下的 ws 协议配置加入到 worker 中
            }
            if (!empty($ws)) {
                EventProvider::addEventListener(WorkerStartEvent::getName(), function () use ($ws) {
                    ob_logger()->info('Workerman 开始加载 WS 协议配置');
                    foreach ($ws as $ws_1) {
                        $worker = new Worker('websocket://' . $ws_1['host'] . ':' . $ws_1['port']);
                        $worker->reusePort = true;
                        $worker->token = ob_uuidgen();
                        $this->ws_socket[] = (new WSServerSocket($worker))->setFlag(1);
                        // ws server 相关事件
                        $worker->onWebSocketConnect = [TopEventListener::getInstance(), 'onWebSocketOpen'];
                        $worker->onClose = [TopEventListener::getInstance(), 'onWebSocketClose'];
                        $worker->onMessage = [TopEventListener::getInstance(), 'onWebSocketMessage'];
                        $worker->listen();
                    }
                }, 999);
            }
            $this->ws_socket[] = (new WSServerSocket($worker))->setFlag(1);

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
                $worker = new Worker('http://' . $http_0['host'] . ':' . $http_0['port']);
                $worker->count = $this->getParam('workerman_worker_num', 1);
                Worker::$internal_running = true;
                // http server 相关事件
                $worker->onMessage = [TopEventListener::getInstance(), 'onHttpRequest'];
                // worker 相关事件
                $worker->onWorkerStart = [TopEventListener::getInstance(), 'onWorkerStart'];
                $worker->onWorkerStop = [TopEventListener::getInstance(), 'onWorkerStop'];
                $worker->token = ob_uuidgen();
                $worker->flag = $http_0['flag'] ?? 1;
                $this->http_socket[] = (new HttpServerSocket($worker, $http_0['port']))->setFlag(1);
            }
            if (!empty($http_pending)) {
                EventProvider::addEventListener(WorkerStartEvent::getName(), function () use ($http_pending) {
                    ob_logger()->info('Workerman 开始加载 HTTP 协议配置');
                    foreach ($http_pending as $http_1) {
                        $worker = new Worker('http://' . $http_1['host'] . ':' . $http_1['port']);
                        $worker->reusePort = true;
                        $worker->token = ob_uuidgen();
                        $worker->flag = $http_1['flag'] ?? 1;
                        $this->http_socket[] = (new HttpServerSocket($worker, $http_1['port']))->setFlag(1);
                        // http server 相关事件
                        $worker->onMessage = [TopEventListener::getInstance(), 'onHttpRequest'];
                        $worker->listen();
                    }
                }, 998);
            }
        }
        /* @noinspection DuplicatedCode */
        foreach ($http_webhook as $v) {
            $this->http_webhook_socket[] = (new HttpWebhookSocket($v['url'], $v['header'] ?? [], $v['access_token'] ?? '', $v['timeout'] ?? 5))->setFlag(1);
        }
        foreach ($ws_reverse as $v) {
            $this->ws_reverse_socket[] = (new WSReverseSocket($v['url'], $v['header'] ?? [], $v['access_token'] ?? '', $v['reconnect_interval'] ?? 5))->setFlag(1);
        }
        return [$this->http_socket !== [], $this->http_webhook_socket !== [], $this->ws_socket !== [], $this->ws_reverse_socket !== []];
    }
}
