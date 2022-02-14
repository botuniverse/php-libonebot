<?php

declare(strict_types=1);

namespace OneBot\Driver;

use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Http\Client\StreamClient;
use OneBot\Http\Client\SwooleClient;
use OneBot\Util\Utils;
use OneBot\V12\Config\ConfigInterface;

abstract class Driver
{
    /** @var ConfigInterface 配置实例 */
    protected $config;

    /** @var string 默认客户端类 */
    protected $default_client_class;

    /** @var string 替代客户端类 */
    protected $alt_client_class;

    /** @var array 事件 */
    protected $_events = [];

    /**
     * @var string
     */
    private static $active_driver_class = WorkermanDriver::class;

    private $driver_init_policy = DriverInitPolicy::MULTI_PROCESS_INIT_IN_FIRST_WORKER;

    /**
     * 创建新的驱动实例
     *
     * @param string $default_client_class 默认客户端类
     * @param string $alt_client_class     替代客户端类
     */
    public function __construct(string $default_client_class = SwooleClient::class, string $alt_client_class = StreamClient::class)
    {
        $this->default_client_class = $default_client_class;
        $this->alt_client_class = $alt_client_class;
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

    public function setDriverInitPolicy(int $policy): Driver
    {
        $this->driver_init_policy = $policy;
        return $this;
    }

    public function getDriverInitPolicy(): int
    {
        return $this->driver_init_policy;
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
}
