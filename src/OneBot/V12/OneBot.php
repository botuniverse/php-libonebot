<?php

declare(strict_types=1);

namespace OneBot\V12;

use OneBot\Driver\Driver;
use OneBot\Driver\Event\EventProvider;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Event\WebSocket\WebSocketMessageEvent;
use OneBot\Driver\Event\WebSocket\WebSocketOpenEvent;
use OneBot\Util\Singleton;
use OneBot\V12\Action\ActionBase;
use OneBot\V12\Config\ConfigInterface;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Event\OneBotEvent;
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

    /** @var LoggerInterface 日志实例 */
    private $logger;

    /** @var string 实现名称 */
    private $implement_name;

    /** @var string 实现平台 */
    private $platform;

    /** @var string 机器人 ID */
    private $self_id;

    /** @var Driver 驱动实例 */
    private $driver;

    /** @var null|ActionBase 动作处理器 */
    private $action_handler;

    /**
     * 创建一个 OneBot 实例
     */
    public function __construct(ConfigInterface $config)
    {
        if (isset(self::$instance)) {
            throw new RuntimeException('只能有一个OneBot实例！');
        }

        $this->config = $config;
        $this->implement_name = $config->get('name');
        $this->self_id = $config->get('self_id');
        $this->platform = $config->get('platform');

        $this->logger = $config->get('logger');
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
        return $this->logger;
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
    public function getActionHandler(): ?ActionBase
    {
        return $this->action_handler;
    }

    /**
     * 设置动作处理器，用于处理 Action 的类（继承自 ActionBase 的类）
     * @param  ActionBase|string $handler 动作处理器
     * @throws OneBotException
     */
    public function setActionHandler($handler): OneBot
    {
        if (is_string($handler) && is_a($handler, ActionBase::class, true)) {
            $this->action_handler = new $handler();
        } elseif ($handler instanceof ActionBase) {
            $this->action_handler = $handler;
        } else {
            throw new OneBotException('CoreActionHandler必须extends ' . ActionBase::class);
        }
        return $this;
    }

    /**
     * 触发 OneBot 事件
     * @todo 该做的东西
     */
    public function dispatchEvent(OneBotEvent $event): void
    {
    }

    /**
     * 运行 OneBot 及 Driver 服务
     */
    public function run(): void
    {
        $this->driver->initDriverProtocols($this->config->getEnabledCommunications());
        $this->addOneBotEvent();
        //Driver::setLogger($this->logger);
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
        // 监听 HTTP 请求事件给驱动
        EventProvider::addEventListener(HttpRequestEvent::getName(), [OneBotEventListener::getInstance(), 'onHttpRequest'], defined('ONEBOT_EVENT_LEVEL') ? ONEBOT_EVENT_LEVEL : 15);
        // 监听 WS 服务器接入事件给驱动
        EventProvider::addEventListener(WebSocketOpenEvent::getName(), [OneBotEventListener::getInstance(), 'onWebSocketOpen'], defined('ONEBOT_EVENT_LEVEL') ? ONEBOT_EVENT_LEVEL : 15);
        // 监听 WS 服务器消息事件给驱动
        EventProvider::addEventListener(WebSocketMessageEvent::getName(), [OneBotEventListener::getInstance(), 'onWebSocketMessage'], defined('ONEBOT_EVENT_LEVEL') ? ONEBOT_EVENT_LEVEL : 15);
    }
}
