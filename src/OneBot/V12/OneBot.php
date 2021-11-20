<?php

declare(strict_types=1);

namespace OneBot\V12;

use OneBot\Util\Singleton;
use OneBot\V12\Action\ActionBase;
use OneBot\V12\Config\ConfigInterface;
use OneBot\V12\Driver\Driver;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Event\OneBotEvent;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class OneBot, this is the main class of LibOneBot.
 */
class OneBot implements LoggerAwareInterface
{
    use Singleton;
    use LoggerAwareTrait;

    /** @var string */
    private $implement_name;

    /** @var string */
    private $platform;

    /** @var null|Driver */
    private $driver;

    /** @var null|ActionBase */
    private $action_handler;
    /**
     * @var string
     */
    private $self_id;

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

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getDriver(): ?Driver
    {
        return $this->driver;
    }

    public function setDriver(Driver $driver, ConfigInterface $config): OneBot
    {
        $this->driver = $driver;
        $this->driver->setConfig($config);
        return $this;
    }

    public function getActionHandler(): ?ActionBase
    {
        return $this->action_handler;
    }

    /**
     * @param mixed $handler
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
        $this->driver->emitOBEvent($event);
    }

    /**
     * @throws OneBotException
     */
    public function run()
    {
        if ($this->driver === null) {
            throw new OneBotException('你需要指定一种驱动器');
        }
        $this->driver->initComm();
        $this->driver->run();
    }

    /**
     * @return string
     */
    public function getImplementName(): string
    {
        return $this->implement_name;
    }

    /**
     * @return string
     */
    public function getSelfId(): string
    {
        return $this->self_id;
    }
}
