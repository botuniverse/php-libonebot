<?php


namespace OneBot\V12;

use OneBot\V12\Action\ActionBase;
use OneBot\V12\Driver\Config\Config;
use OneBot\V12\Driver\Driver;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\EventObject;

/**
 * Class OneBot
 * @package OneBot\V12
 */
class OneBot
{
    /** @var null|OneBot */
    private static $obj = null;
    /** @var string */
    private $implement_name;
    /** @var string */
    private $platform;
    /** @var Driver|null */
    private $driver = null;
    /** @var ActionBase|null */
    private $action_handler = null;

    /**
     * OneBot constructor.
     * @param $implement_name
     * @param string $platform
     * @throws OneBotException
     */
    public function __construct($implement_name, string $platform = 'default') {
        $this->implement_name = $implement_name;
        $this->platform = $platform;
        if (self::$obj !== null) throw new OneBotException("只能有一个OneBot实例！");
        self::$obj = $this;
    }

    /**
     * @return OneBot|null
     */
    public static function getInstance(): ?OneBot {
        return self::$obj;
    }

    public function setServerDriver(Driver $driver, Config $config): OneBot {
        $this->driver = $driver;
        $this->driver->setConfig($config);
        return $this;
    }

    public function callOBEvent(EventObject $event) {
        $this->driver->emitOBEvent($event);
    }

    /**
     * @throws OneBotException
     */
    public function run() {
        if ($this->driver === null) throw new OneBotException("你需要指定一种驱动器");
        $this->driver->initComm();
        $this->driver->run();
    }

    public function getActionHandler(): ?ActionBase {
        return $this->action_handler;
    }

    /**
     * @throws OneBotException
     */
    public function setActionHandler($handler): OneBot {
        if (is_string($handler) && is_a($handler, ActionBase::class, true)) {
            $this->action_handler = new $handler();
        } elseif ($handler instanceof ActionBase) {
            $this->action_handler = $handler;
        } else {
            throw new OneBotException("CoreActionHandler必须extends ".ActionBase::class);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getPlatform(): string {
        return $this->platform;
    }
}