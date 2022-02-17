<?php

declare(strict_types=1);

namespace OneBot\Driver;

use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Util\Utils;
use OneBot\V12\Config\ConfigInterface;

abstract class Driver
{
    public const SINGLE_PROCESS = 0;

    public const MULTI_PROCESS = 1;

    /**
     * @var WebSocketClientInterface
     */
    public $ws_reverse_client;

    /** @var array */
    public $ws_reverse_client_params;

    /** @var ConfigInterface 配置实例 */
    protected $config;

    /** @var array 事件 */
    protected $_events = [];

    /**
     * @var string
     */
    private static $active_driver_class = WorkermanDriver::class;

    /**
     * @var array
     */
    private $params;

    /**
     * 创建新的驱动实例
     */
    public function __construct(array $params = [])
    {
        $this->params = $params;
        self::$active_driver_class = static::class;
    }

    public static function getActiveDriverClass(): string
    {
        return self::$active_driver_class;
    }

    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return Utils::camelToSeparator(str_replace('Driver', '', static::class));
    }

    /**
     * 设置配置实例
     *
     * @param ConfigInterface $config 配置实例
     */
    public function setConfig(ConfigInterface $config): void
    {
        $this->config = $config;
    }

    /**
     * 获取配置实例
     */
    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    public function getDriverInitPolicy(): int
    {
        return $this->getParam('driver_init_policy', DriverInitPolicy::MULTI_PROCESS_INIT_IN_FIRST_WORKER);
    }

    /**
     * 初始化通讯
     *
     * @param array $comm 启用的通讯方式
     */
    abstract public function initDriverProtocols(array $comm): void;

    /**
     * 运行驱动
     */
    abstract public function run(): void;

    abstract public function getHttpWebhookUrl(): string;

    abstract public function getWSReverseClient(): ?WebSocketClientInterface;

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParam($key, $default)
    {
        return $this->params[$key] ?? $default;
    }
}
