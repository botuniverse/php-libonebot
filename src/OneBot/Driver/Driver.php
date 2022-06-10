<?php

declare(strict_types=1);

namespace OneBot\Driver;

use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Util\Utils;
use OneBot\V12\Config\ConfigInterface;
use Psr\Http\Message\UriInterface;

abstract class Driver
{
    public const SINGLE_PROCESS = 0;

    public const MULTI_PROCESS = 1;

    /**
     * @var array
     */
    public $http_webhook_config;

    /**
     * @var WebSocketClientInterface
     */
    public $ws_reverse_client;

    /** @var array */
    public $ws_reverse_config;

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

    /**
     * 获取当前活动的 Driver 类
     */
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
        $ws_index = null;
        $http_index = null;
        $has_http_webhook = null;
        $has_ws_reverse = null;
        foreach ($comm as $k => $v) {
            switch ($v['type']) {
                case 'websocket':
                    $ws_index = $v;
                    break;
                case 'http':
                    $http_index = $v;
                    break;
                case 'http_webhook':
                    $has_http_webhook = $v;
                    break;
                case 'ws_reverse':
                    $has_ws_reverse = $v;
                    break;
            }
        }
        [$http, $webhook, $ws, $ws_reverse] = $this->initInternalDriverClasses($http_index, $has_http_webhook, $ws_index, $has_ws_reverse);
        $ws ? ob_logger()->info('已开启正向 WebSocket，监听地址 ' . $ws_index['host'] . ':' . $ws_index['port']) : ob_logger()->debug('未开启正向 WebSocket');
        $http ? ob_logger()->info('已开启 HTTP，监听地址 ' . $http_index['host'] . ':' . $http_index['port']) : ob_logger()->debug('未开启 HTTP');
        $webhook ? ob_logger()->info('已开启 HTTP Webhook，地址 ' . $has_http_webhook['url']) : ob_logger()->debug('未开启 HTTP Webhook');
        $ws_reverse ? ob_logger()->info('已开启反向 WebSocket，地址 ' . $has_ws_reverse['url']) : ob_logger()->debug('未开启反向 WebSocket');
    }

    /**
     * 获取 HTTP Webhook 的配置文件
     */
    public function getHttpWebhookConfig(): array
    {
        return $this->http_webhook_config;
    }

    /**
     * 获取反向 WS 建立的连接操作对象
     *
     * @return WebSocketClientInterface
     */
    public function getWSReverseClient(): ?WebSocketClientInterface
    {
        return $this->ws_reverse_client;
    }

    /**
     * 获取反向 WS 通信方式的配置文件
     */
    public function getWSReverseConfig(): array
    {
        return $this->ws_reverse_config;
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

    /**
     * 运行驱动
     */
    abstract public function run(): void;

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
     * @param string|UriInterface $address 目标地址
     * @param array               $header  请求头
     */
    abstract public function initWebSocketClient($address, array $header = []): WebSocketClientInterface;

    /**
     * 通过解析的配置，让 Driver 初始化不同的通信方式
     *
     * 当传入的任一参数不为 null 时，表明此通信方式启用。
     */
    abstract protected function initInternalDriverClasses(?array $http, ?array $http_webhook, ?array $ws, ?array $ws_reverse): array;
}
