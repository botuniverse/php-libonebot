<?php

declare(strict_types=1);

namespace OneBot\V12;

use OneBot\Config\Config;
use OneBot\Config\RepositoryInterface;
use OneBot\Driver\Driver;
use Psr\Log\LoggerInterface;

/**
 * OneBotBuilder 是用于生成/构建 OneBot 实例的工厂类
 *
 * 可选择通过 `OneBotBuilder::factory()` 以链式工厂构建 OneBot 实例；
 * 也可以通过 `OneBotBuilder::buildFromArray()` 或 `OneBotBuilder::buildFromConfig()` 进行构建。
 */
class OneBotBuilder
{
    /** @var array 定义的组件 */
    private array $components;

    /**
     * 工厂模式链式构建起始函数
     */
    public static function factory(): self
    {
        return new self();
    }

    /**
     * 设置 OneBot 名称
     *
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->components['name'] = $name;
        return $this;
    }

    /**
     * 设置 OneBot 平台名称
     *
     * 例如 qq，kaiheila，discord 等。
     *
     * @return $this
     */
    public function setPlatform(string $platform): self
    {
        $this->components['platform'] = $platform;
        return $this;
    }

    /**
     * 设置 OneBot 机器人自身的 ID
     *
     * 此处可能无法先调用知道，可能需要保留在后面 Driver 初始化，机器人端 API 实现连接完毕后设置。
     *
     * @param  string $self_id 机器人自身 ID
     * @return $this
     */
    public function setSelfId(string $self_id): self
    {
        $this->components['self_id'] = $self_id;
        return $this;
    }

    /**
     * 设置自定义的 Logger 组件
     *
     * @param  array|object|string $logger Logger 实例、类名或类传参数组
     * @return $this
     */
    public function useLogger($logger): self
    {
        $this->components['logger'] = self::resolveClassInstance($logger, LoggerInterface::class);
        return $this;
    }

    /**
     * 设置自定义的 Driver 底层协议驱动器
     *
     * @param  array|object|string $driver Driver 实例、类名或类传参数组
     * @return $this
     */
    public function useDriver($driver): self
    {
        $this->components['driver'] = self::resolveClassInstance($driver, Driver::class);
        return $this;
    }

    /**
     * 设置要启动的通信协议
     *
     * @param  array $protocols 通信协议启动的数组
     * @return $this
     */
    public function setCommunicationsProtocol(array $protocols): self
    {
        array_map([$this, 'addCommunicationProtocol'], $protocols);
        return $this;
    }

    /**
     * 从工厂模式开始初始化 OneBot 对象，并进一步启动 OneBot 实现
     */
    public function build(): OneBot
    {
        $required_config = ['name', 'platform', 'self_id', 'logger', 'driver', 'communications'];

        if (array_keys($this->components) !== $required_config) {
            $missing = implode(', ', array_diff($required_config, array_keys($this->components)));
            throw new \InvalidArgumentException('Builder must be configured before building, missing: ' . $missing);
        }

        $config = new Config([
            'name' => $this->components['name'],
            'platform' => $this->components['platform'],
            'self_id' => $this->components['self_id'],
            'logger' => $this->components['logger'],
            'driver' => $this->components['driver'],
            'communications' => $this->components['communications'],
        ]);

        return new OneBot($config);
    }

    /**
     * 从数组格式的配置文件实例化 OneBot 对象
     *
     * 内部将自动转换为 Repository 对象再依次调用 buildFromConfig()。
     *
     * @param  array  $array config 数组
     * @return OneBot OneBot 对象
     */
    public static function buildFromArray(array $array): OneBot
    {
        $config = new \OneBot\Config\Repository($array);
        return self::buildFromConfig($config);
    }

    /**
     * 从 Repository 对象实例化 OneBot 对象
     *
     * 首先会对 config 中的 'logger' 类实例化，然后对 Driver 类进行实例化。
     * 实例化后可以通过 $config 进行获取相应对象。
     *
     * @param  RepositoryInterface $config Repository 对象
     * @return OneBot              OneBot 对象
     */
    public static function buildFromConfig(RepositoryInterface $config): OneBot
    {
        $config->set('logger', self::resolveClassInstance($config->get('logger'), LoggerInterface::class));
        $config->set('driver', self::resolveClassInstance($config->get('driver'), Driver::class));

        return new OneBot($config);
    }

    /**
     * 通过给出的 Class Name 返回该 Class 的实例，同时第二个参数用于做验证类型，是否是对应类型
     *
     * $class 参数可以传入对象，传入对象时直接验证后返回本身。
     * 传入类名称时直接new返回。
     * 传入array时，数组中第一个值代表类名称，第二个值代表构造参数列表，会在new class时被当作参数传入。
     * 传入其他类型会抛出异常。
     *
     * @param  array|object|string $class    参数类
     * @param  string              $expected 期望类型，用于验证
     * @return mixed               返回实例对象
     */
    protected static function resolveClassInstance($class, string $expected)
    {
        if ($class instanceof $expected) {
            return $class;
        }
        // TODO：这里是不是缺一个对string和array传入类型的验证，要不然expected就搁那晒太阳
        if (is_string($class)) {
            return new $class();
        }

        if (is_array($class)) {
            $classname = array_shift($class);
            $parameters = array_shift($class);
            if ($parameters) {
                return new $classname($parameters);
            }
            return new $classname();
        }

        throw new \InvalidArgumentException("Cannot resolve {$expected}, it must be an instance, a class name or an array containing a class name and an array of parameters");
    }

    /**
     * 添加配置文件到对象里
     *
     * @param  array $config 配置数组
     * @return $this
     */
    private function addCommunicationProtocol(array $config): self
    {
        $this->components['communications'][] = $config;
        return $this;
    }
}
