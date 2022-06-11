<?php

declare(strict_types=1);

namespace OneBot\Driver;

use Exception;
use OneBot\Driver\Event\DriverInitEvent;
use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\EventProvider;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Event\Process\UserProcessStartEvent;
use OneBot\Driver\Event\WebSocket\WebSocketCloseEvent;
use OneBot\Driver\Event\WebSocket\WebSocketMessageEvent;
use OneBot\Driver\Event\WebSocket\WebSocketOpenEvent;
use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Driver\Workerman\TopEventListener;
use OneBot\Driver\Workerman\UserProcess;
use OneBot\Driver\Workerman\WebSocketClient;
use OneBot\Driver\Workerman\Worker;
use OneBot\Http\HttpFactory;
use OneBot\Http\WebSocket\FrameFactory;
use OneBot\Http\WebSocket\FrameInterface;
use OneBot\Http\WebSocket\Opcode;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;
use Workerman\Timer;

class WorkermanDriver extends Driver
{
    /** @var Worker HTTP Worker */
    protected $http_worker;

    /** @var Worker WS Worker */
    protected $ws_worker;

    /**
     * 通过传入的配置文件初始化 Driver 下面的协议相关事件
     */
    public function initInternalDriverClasses(?array $http, ?array $http_webhook, ?array $ws, ?array $ws_reverse): array
    {
        if ($ws !== null) {
            $this->ws_worker = new Worker('websocket://' . $ws['host'] . ':' . $ws['port']);
            $this->ws_worker->count = $ws['worker_count'] ?? 4;
            Worker::$internal_running = true;  // 不可以删除这句话哦
            $this->initWebSocketServer();
            $this->initServer($this->ws_worker);
            if ($http !== null) {
                ob_logger()->warning('在 Workerman 驱动下不可以同时开启 http 和 websocket 模式，将优先开启 websocket');
            }
            ob_logger()->info('已开启正向 WebSocket，监听地址 ' . $ws['host'] . ':' . $ws['port']);
        } elseif ($http !== null) {
            // 定义 Workerman 的 worker 和相关回调
            $this->http_worker = new Worker('http://' . $http['host'] . ':' . $http['port']);
            $this->http_worker->count = $http['worker_count'] ?? 4;
            Worker::$internal_running = true; // 加上这句就可以不需要必须输 start 命令才能启动了，直接启动
            $this->initHttpServer();
            $this->initServer($this->http_worker);
            ob_logger()->info('已开启 HTTP，监听地址 ' . $http['host'] . ':' . $http['port']);
        }
        if ($http_webhook !== null) {
            ob_logger()->info('已开启 HTTP Webhook，地址 ' . $http_webhook['url']);
            $this->http_webhook_config = $http_webhook;
        }
        if ($ws_reverse !== null) {
            ob_logger()->info('已开启反向 WebSocket，地址 ' . $ws_reverse['url']);
            $this->ws_reverse_config = $ws_reverse;
        }
        return [$this->http_worker !== null, $this->http_webhook_config !== null, $this->ws_worker !== null, $this->ws_reverse_config !== null];
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
            // TODO: 编写纯 WS Reverse 连接下的逻辑，就是不启动 Server 的
            if ($this->ws_worker === null && $this->http_worker === null) {
                $worker = new Worker();
                Worker::$internal_running = true; // 加上这句就可以不需要必须输 start 命令才能启动了，直接启动
                $this->initServer($worker);
            }
            // 启动 Workerman 下的 Worker 们
            Worker::runAll();
        } catch (Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
        }
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
    public function initWebSocketClient($address, array $header = []): WebSocketClientInterface
    {
        return $this->ws_reverse_client = WebSocketClient::createFromAddress($address, $header);
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
                    $response->withBody($psr_response->getBody()->getContents());
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
                $frame = FrameFactory::createTextFrame($data);
                $event = new WebSocketMessageEvent($connection->id, $frame, function (int $fd, $data) use ($connection) {
                    if ($data instanceof FrameInterface) {
                        $data_w = $data->getData();
                        return $connection->send($data_w, $data->getOpcode() === Opcode::TEXT);
                    }
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

    private function initServer(Worker $worker)
    {
        $worker->onWorkerStart = [TopEventListener::getInstance(), 'onWorkerStart'];
        $worker->onWorkerStop = [TopEventListener::getInstance(), 'onWorkerStop'];
    }
}
