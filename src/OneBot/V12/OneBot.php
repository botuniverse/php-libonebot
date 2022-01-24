<?php

declare(strict_types=1);

namespace OneBot\V12;

use OneBot\Driver\Driver;
use OneBot\Driver\Event\Event;
use OneBot\Driver\Event\EventProvider;
use OneBot\Util\Singleton;
use OneBot\V12\Action\ActionBase;
use OneBot\V12\Config\ConfigInterface;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Event\OneBotEvent;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * OneBot 入口类
 * 一切从这里开始，这句话是真人写的，不是AI写的
 */
class OneBot implements LoggerAwareInterface
{
    use Singleton;
    use LoggerAwareTrait;

    /** @var ConfigInterface 配置文件对象 */
    private $config;

    /** @var string 实现名称 */
    private $implement_name;

    /** @var string 实现平台 */
    private $platform;

    /** @var string 机器人 ID */
    private $self_id;

    /** @var null|Driver 驱动器 */
    private $driver;

    /** @var null|ActionBase 动作处理器 */
    private $action_handler;

    /**
     * OneBot constructor.
     *
     * @throws OneBotException
     */
    public function __construct(string $implement_name, string $platform = 'default', string $self_id = 'default')
    {
        $this->implement_name = $implement_name;
        $this->self_id = $self_id;
        $this->platform = $platform;
        if (isset(self::$instance)) {
            throw new OneBotException('只能有一个OneBot实例！');
        }
        self::$instance = $this;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getImplementName(): string
    {
        return $this->implement_name;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getSelfId(): string
    {
        return $this->self_id;
    }

    public function getDriver(): ?Driver
    {
        return $this->driver;
    }

    public function setDriver(Driver $driver, ConfigInterface $config): OneBot
    {
        $this->driver = $driver;
        $this->setConfig($config);
        return $this;
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    public function setConfig(ConfigInterface $config): void
    {
        $this->config = $config;
    }

    public function getActionHandler(): ?ActionBase
    {
        return $this->action_handler;
    }

    /**
     * 设置动作处理器
     *
     * @param ActionBase|string $handler 动作处理器
     *
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

    public function callOBEvent(OneBotEvent $event)
    {
    }

    /**
     * 运行服务
     *
     * @throws OneBotException
     */
    public function run()
    {
        if ($this->driver === null) {
            throw new OneBotException('你需要指定一种驱动器');
        }
        $this->driver->initDriverProtocols($this->config->getEnabledCommunications());
        $this->addOneBotEvent();
        $this->driver->run();
    }

    private function addOneBotEvent()
    {
        EventProvider::addEventListener(Event::EVENT_HTTP_REQUEST, [OneBotEventListener::class, 'onHttpRequest']);
        EventProvider::addEventListener(Event::EVENT_WEBSOCKET_OPEN, [OneBotEventListener::class, 'onWebSocketOpen']);
    }
}
