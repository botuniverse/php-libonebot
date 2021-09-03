<?php


namespace OneBot\V12;

use OneBot\V12\Driver\Config\Config;
use OneBot\V12\Driver\Driver;
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
    private $name;
    /** @var Driver|null */
    private $driver = null;
    /** @var CoreActionInterface|null */
    private $core_action_handler = null;
    /** @var array */
    private $extended_actions = [];

    /**
     * OneBot constructor.
     * @param $name
     * @param Config $config
     * @throws OneBotException
     */
    public function __construct($name) {
        $this->name = $name;
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

    /**
     * @param $handler
     * @return $this
     * @throws OneBotException
     */
    public function setCoreActionHandler($handler): OneBot {
        if (is_string($handler) && is_a($handler, CoreActionInterface::class, true)) {
            $this->core_action_handler = new $handler();
        } elseif ($handler instanceof CoreActionInterface) {
            $this->core_action_handler = $handler;
        } else {
            throw new OneBotException("CoreActionHandler必须implements ".CoreActionInterface::class);
        }
        return $this;
    }

    public function setExtendedAction($action_name, ExtendedActionInterface $handler, $method_name = null): OneBot {
        $mixed_name = $this->name . "_" . $action_name;
        $this->extended_actions[$mixed_name] = [$handler, $method_name ?? Utils::separatorToCamel($mixed_name)];
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
        $this->driver->run();
    }

    /**
     * @return CoreActionInterface|null
     */
    public function getCoreActionHandler(): ?CoreActionInterface {
        return $this->core_action_handler;
    }

    /**
     * @return array
     */
    public function getExtendedActions(): array {
        return $this->extended_actions;
    }
}