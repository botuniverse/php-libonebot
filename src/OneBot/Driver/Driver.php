<?php

declare(strict_types=1);

namespace OneBot\Driver;

use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Driver\Socket\SocketTrait;
use OneBot\V12\Config\ConfigInterface;

abstract class Driver
{
    use SocketTrait;

    public const SINGLE_PROCESS = 0;

    public const MULTI_PROCESS = 1;

    public const SUPPORTED_CLIENTS = [];

    /**
     * @var WebSocketClientInterface
     */
    public $ws_reverse_client;

    /** @var ConfigInterface 配置实例 */
    protected $config;

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

    /**
     * 获取当前活动的 Driver 类
     */
    public static function getActiveDriverClass(): string
    {
        return self::$active_driver_class;
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

    /**
     * 获取驱动初始化策略
     */
    public function getDriverInitPolicy(): int
    {
        return $this->getParam('driver_init_policy', DriverInitPolicy::MULTI_PROCESS_INIT_IN_FIRST_WORKER);
    }

    /**
     * 初始化通讯
     *
     * @param array $comm 启用的通讯方式
     */
    public function initDriverProtocols(array $comm)
    {
        $ws_index = [];
        $http_index = [];
        $has_http_webhook = [];
        $has_ws_reverse = [];
        foreach ($comm as $v) {
            switch ($v['type']) {
                case 'websocket':
                case 'ws':
                    $ws_index[] = $v;
                    break;
                case 'http':
                    $http_index[] = $v;
                    break;
                case 'http_webhook':
                case 'webhook':
                    $has_http_webhook[] = $v;
                    break;
                case 'ws_reverse':
                case 'websocket_reverse':
                    $has_ws_reverse[] = $v;
                    break;
            }
        }
        [$http, $webhook, $ws, $ws_reverse] = $this->initInternalDriverClasses($http_index, $has_http_webhook, $ws_index, $has_ws_reverse);
        if ($ws) {
            ob_logger()->info('已开启正向 WebSocket');
        }
        if ($http) {
            ob_logger()->info('已开启 HTTP');
        }
        if ($webhook) {
            ob_logger()->info('已开启 HTTP Webhook');
        }
        if ($ws_reverse) {
            ob_logger()->info('已开启反向 WebSocket');
        }
    }

    /**
     * 获取 Driver 自身传入的配置项（所有）
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * 获取 Driver 自身传入的配置项
     *
     * @param  int|string $key
     * @param  mixed      $default
     * @return mixed
     */
    public function getParam($key, $default)
    {
        return $this->params[$key] ?? $default;
    }

    public function getSupportedClients(): array
    {
        return static::SUPPORTED_CLIENTS;
    }

    /**
     * 运行驱动
     */
    abstract public function run(): void;

    /**
     * 获取驱动名称
     */
    abstract public function getName(): string;

    /**
     * 添加一个定时器
     *
     * @param int      $ms        间隔时间（单位为毫秒）
     * @param callable $callable  回调函数
     * @param int      $times     运行次数（默认只运行一次，如果为0或-1，则将会永久运行）
     * @param array    $arguments 回调要调用的参数
     */
    abstract public function addTimer(int $ms, callable $callable, int $times = 1, array $arguments = []): int;

    /**
     * 删除 Driver 的计时器
     *
     * @param int $timer_id 通过 addTimer() 方法返回的计时器 ID
     */
    abstract public function clearTimer(int $timer_id);

    /**
     * 初始化驱动的 WS Reverse Client 连接
     *
     * @param array $headers 请求头
     */
    abstract public function initWSReverseClients(array $headers = []);

    /**
     * 驱动必须提供一个可以添加到对应驱动 EventLoop 的读接口
     *
     * @param resource $fd       文件描述符或资源 int
     * @param callable $callable 回调函数
     */
    abstract public function addReadEvent($fd, callable $callable);

    /**
     * 驱动必须提供一个可以删除对应驱动 EventLoop 的读接口
     *
     * @param resource $fd 文件描述符或资源 int
     */
    abstract public function delReadEvent($fd);

    /**
     * 通过解析的配置，让 Driver 初始化不同的通信方式
     *
     * 当传入的任一参数不为 null 时，表明此通信方式启用。
     */
    abstract protected function initInternalDriverClasses(?array $http, ?array $http_webhook, ?array $ws, ?array $ws_reverse): array;
}
