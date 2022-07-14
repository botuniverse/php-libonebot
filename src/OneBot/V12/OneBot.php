<?php

declare(strict_types=1);

namespace OneBot\V12;

use OneBot\Driver\Driver;
use OneBot\Driver\Event\DriverInitEvent;
use OneBot\Driver\Event\EventProvider;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Event\Process\ManagerStartEvent;
use OneBot\Driver\Event\Process\ManagerStopEvent;
use OneBot\Driver\Event\Process\WorkerStartEvent;
use OneBot\Driver\Event\Process\WorkerStopEvent;
use OneBot\Driver\Event\WebSocket\WebSocketMessageEvent;
use OneBot\Driver\Event\WebSocket\WebSocketOpenEvent;
use OneBot\Driver\Interfaces\DriverInitPolicy;
use OneBot\Http\WebSocket\FrameFactory;
use OneBot\Util\ObjectQueue;
use OneBot\Util\Singleton;
use OneBot\V12\Action\ActionHandlerBase;
use OneBot\V12\Config\ConfigInterface;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Event\Meta\MetaEvent;
use OneBot\V12\Object\Event\OneBotEvent;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * OneBot 入口类
 * 一切从这里开始，这句话是真人写的，不是AI写的
 */
class OneBot
{
    use Singleton;

    /** @var ConfigInterface 配置实例 */
    private $config;

    /** @var string 实现名称 */
    private $implement_name;

    /** @var string 实现平台 */
    private $platform;

    /** @var string 机器人 ID */
    private $self_id;

    /** @var Driver 驱动实例 */
    private $driver;

    /** @var null|ActionHandlerBase 动作处理器 */
    private $base_action_handler;

    /** @var array 动作处理回调们 */
    private $action_handlers = [];

    /**
     * 创建一个 OneBot 实例
     */
    public function __construct(ConfigInterface $config)
    {
        if (self::$instance !== null) {
            throw new RuntimeException('只能有一个OneBot实例！');
        }

        $this->config = $config;
        $this->implement_name = $config->get('name');
        $this->self_id = $config->get('self_id');
        $this->platform = $config->get('platform');

        ob_logger_register($config->get('logger'));
        $config->set('logger', null);
        $this->driver = $config->get('driver');
        $config->set('driver', null);

        self::$instance = $this;
    }

    /**
     * 获取日志实例
     */
    public function getLogger(): LoggerInterface
    {
        return ob_logger();
    }

    /**
     * 返回 OneBot 实现的名称
     * @see https://12.onebot.dev/onebotrpc/data-protocol/event/
     */
    public function getImplementName(): string
    {
        return $this->implement_name;
    }

    /**
     * 返回平台名称
     * @see https://12.onebot.dev/onebotrpc/data-protocol/event/
     */
    public function getPlatform(): string
    {
        return $this->platform;
    }

    /**
     * 返回 OneBot 实现自身的 ID
     * @see https://12.onebot.dev/onebotrpc/data-protocol/event/
     */
    public function getSelfId(): string
    {
        return $this->self_id;
    }

    /**
     * @param int|string $self_id
     */
    public function setSelfId($self_id): void
    {
        $this->self_id = $self_id;
    }

    /**
     * 获取 Driver
     */
    public function getDriver(): ?Driver
    {
        return $this->driver;
    }

    /**
     * 获取配置实例
     */
    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * 获取动作处理器实例
     */
    public function getBaseActionHandler(): ?ActionHandlerBase
    {
        return $this->base_action_handler;
    }

    /**
     * 设置动作处理器，用于处理 Action 的类（继承自 ActionBase 的类）
     *
     * @param  ActionHandlerBase|string $handler 动作处理器
     * @throws OneBotException
     */
    public function setActionHandlerClass($handler): OneBot
    {
        if (is_string($handler) && is_a($handler, ActionHandlerBase::class, true)) {
            $this->base_action_handler = new $handler();
        } elseif ($handler instanceof ActionHandlerBase) {
            $this->base_action_handler = $handler;
        } else {
            throw new OneBotException('CoreActionHandler必须extends ' . ActionHandlerBase::class);
        }
        return $this;
    }

    /**
     * 动态插入动作处理器
     *
     * @return $this
     */
    public function addActionHandler(string $action, callable $handler, array $options = []): OneBot
    {
        $this->action_handlers[$action] = [$handler, $options];
        return $this;
    }

    /**
     * 获取动态插入的动作处理器
     *
     * @return null|mixed
     */
    public function getActionHandler(string $action)
    {
        return $this->action_handlers[$action] ?? null;
    }

    /**
     * 获取所有动态插入的动作处理器
     */
    public function getActionHandlers(): array
    {
        return $this->action_handlers;
    }

    /**
     * 获取 HTTP Webhook 及反向 WebSocket 连接时请求的 Headers
     *
     * $addition 参数为自定义部分，将会被合并到头内
     */
    public function getRequestHeaders(array $additions = [], string $access_token = ''): array
    {
        $default_headers = [
            'User-Agent' => 'OneBot/12 (' . $this->getPlatform() . ') ' . $this->getImplementName() . '/' . $this->getAppVersion(),
            'X-OneBot-Version' => '12',
            'X-Impl' => $this->getImplementName(),
            'X-Platform' => $this->getPlatform(),
            'X-Self-ID' => $this->getSelfId(),
        ];
        if ($access_token !== '') {
            $default_headers['Authorization'] = 'Bearer ' . $access_token;
        }
        return array_merge($default_headers, $additions);
    }

    /**
     * 获取 OneBot 实现的版本
     *
     * 通过 ONEBOT_APP_VERSION 常量定义，需自行在项目内 define 。
     */
    public function getAppVersion(): string
    {
        return defined('ONEBOT_APP_VERSION') ? ONEBOT_APP_VERSION : ONEBOT_LIBOB_VERSION;
    }

    /**
     * 触发 OneBot 事件，通过已知的方法发送事件
     *
     * 此方法默认会将 MetaEvent 排除，如果需要分发 MetaEvent 请自行获取 Driver 的 Socket 进行发送。
     * 首先会对 HTTP Webhook 进行分发，并优先采用异步发送，如果没有支持异步的 Client，则会采用同步发送。
     * 然后对所有连接到正向 WS 的客户端挨个发送一遍。
     * 最后对所有连接到反向 WS 的发送一遍。
     *
     * @param OneBotEvent $event 事件对象
     */
    public function dispatchEvent(OneBotEvent $event): void
    {
        ob_logger()->info('Dispatching event: ' . $event->type);
        if (!$event instanceof MetaEvent) { // 排除 meta_event，要不然队列速度爆炸
            ObjectQueue::enqueue('ob_event', $event);
        }
        foreach ($this->driver->getHttpWebhookSockets() as $socket) {
            if ($socket->getFlag() !== 1) {
                continue;
            }
            $socket->post(json_encode($event->jsonSerialize()), $this->getRequestHeaders(), function (ResponseInterface $response) {
                // TODO：编写 HTTP Webhook 响应的处理逻辑
            }, function (RequestInterface $request) {});
        }
        $frame_str = FrameFactory::createTextFrame(json_encode($event->jsonSerialize())); // 创建文本帧
        foreach ($this->driver->getWSServerSockets() as $socket) {
            if ($socket->getFlag() !== 1) {
                continue;
            }
            $socket->sendAll($frame_str);
        }
        foreach ($this->driver->getWSReverseSockets() as $socket) {
            if ($socket->getFlag() !== 1) {
                continue;
            }
            $socket->send($frame_str);
        }
    }

    /**
     * 运行 OneBot 及 Driver 服务
     */
    public function run(): void
    {
        $this->driver->initDriverProtocols($this->config->getEnabledCommunications());
        $this->addOneBotEvent();

        ObjectQueue::limit('ob_event', 99999);
        $this->driver->run();
    }

    /**
     * 这里是 OneBot 实现本身添加到 Driver 的事件
     * 包含 HTTP 服务器接收 Request 和 WebSocket 服务器收到连接的事件
     * 对应事件 id 为 http.request, websocket.open
     * 如果你要二次开发或者添加新的事件，可以先继承此类，然后重写此方法
     */
    protected function addOneBotEvent()
    {
        if (!defined('ONEBOT_EVENT_LEVEL')) {
            define('ONEBOT_EVENT_LEVEL', 15);
        }
        // 监听 HTTP 服务器收到的请求事件
        EventProvider::addEventListener(HttpRequestEvent::getName(), [OneBotEventListener::getInstance(), 'onHttpRequest'], ONEBOT_EVENT_LEVEL);
        // 监听 WS 服务器相关事件
        EventProvider::addEventListener(WebSocketOpenEvent::getName(), [OneBotEventListener::getInstance(), 'onWebSocketOpen'], ONEBOT_EVENT_LEVEL);
        EventProvider::addEventListener(WebSocketMessageEvent::getName(), [OneBotEventListener::getInstance(), 'onWebSocketMessage'], ONEBOT_EVENT_LEVEL);
        // 监听 Worker 进程退出或启动的事件
        EventProvider::addEventListener(WorkerStartEvent::getName(), [OneBotEventListener::getInstance(), 'onWorkerStart'], ONEBOT_EVENT_LEVEL);
        EventProvider::addEventListener(WorkerStopEvent::getName(), [OneBotEventListener::getInstance(), 'onWorkerStop'], ONEBOT_EVENT_LEVEL);
        // 监听 Manager 进程退出或启动事件（仅限 Swoole 驱动下的 SWOOLE_PROCESS 模式才能触发）
        EventProvider::addEventListener(ManagerStartEvent::getName(), [OneBotEventListener::getInstance(), 'onManagerStart'], ONEBOT_EVENT_LEVEL);
        EventProvider::addEventListener(ManagerStopEvent::getName(), [OneBotEventListener::getInstance(), 'onManagerStop'], ONEBOT_EVENT_LEVEL);
        // 监听单进程无 Server 模式的相关事件（如纯 Client 情况下的启动模式）
        EventProvider::addEventListener(DriverInitEvent::getName(), [OneBotEventListener::getInstance(), 'onDriverInit'], ONEBOT_EVENT_LEVEL);
        // 如果Init策略是FirstWorker，则给WorkerStart添加添加相关事件，让WorkerStart事件（#0）中再套娃执行DriverInit事件
        switch ($this->driver->getDriverInitPolicy()) {
            case DriverInitPolicy::MULTI_PROCESS_INIT_IN_FIRST_WORKER:
                EventProvider::addEventListener(WorkerStartEvent::getName(), [OneBotEventListener::getInstance(), 'onFirstWorkerInit'], ONEBOT_EVENT_LEVEL);
                break;
        }
    }
}
