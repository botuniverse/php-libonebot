<?php

declare(strict_types=1);

namespace OneBot\V12;

use InvalidArgumentException;
use OneBot\Driver\Driver;
use OneBot\V12\Config\ConfigInterface;
use Psr\Log\LoggerInterface;

class OneBotBuilder
{
    /** @var array 定义的组件 */
    private $components;

    public static function factory(): self
    {
        return new self();
    }

    public function setName(string $name): self
    {
        $this->components['name'] = $name;
        return $this;
    }

    public function setPlatform(string $platform): self
    {
        $this->components['platform'] = $platform;
        return $this;
    }

    public function setSelfId(string $selfId): self
    {
        $this->components['selfId'] = $selfId;
        return $this;
    }

    public function useLogger($logger): self
    {
        $this->components['logger'] = self::resolveClassInstance($logger, LoggerInterface::class);
        return $this;
    }

    public function useDriver($driver): self
    {
        $this->components['driver'] = self::resolveClassInstance($driver, Driver::class);
        return $this;
    }

    public function addCommunicationProtocol(array $config): self
    {
        $this->components['communications'][] = $config;
        return $this;
    }

    public function setCommunicationsProtocol(array $protocols): self
    {
        array_map([$this, 'addCommunicationProtocol'], $protocols);
        return $this;
    }

    public function build(): OneBot
    {
        $required_config = ['name', 'platform', 'selfId', 'logger', 'driver', 'communications'];

        if (array_keys($this->components) !== $required_config) {
            $missing = implode(', ', array_diff($required_config, array_keys($this->components)));
            throw new InvalidArgumentException('Builder must be configured before building, missing: ' . $missing);
        }

        $config = new Config\Config([
            'name' => $this->components['name'],
            'platform' => $this->components['platform'],
            'selfId' => $this->components['selfId'],
            'logger' => $this->components['logger'],
            'driver' => $this->components['driver'],
            'communications' => $this->components['communications'],
        ]);

        return new OneBot($config);
    }

    public static function buildFromArray(array $array): OneBot
    {
        $config = new Config\Config($array);
        return self::buildFromConfig($config);
    }

    public static function buildFromConfig(ConfigInterface $config): OneBot
    {
        $config->set('logger', self::resolveClassInstance($config->get('logger'), LoggerInterface::class));
        $config->set('driver', self::resolveClassInstance($config->get('driver'), Driver::class));

        return new OneBot($config);
    }

    private static function resolveClassInstance($class, $expected)
    {
        if ($class instanceof $expected) {
            return $class;
        }

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

        throw new InvalidArgumentException("Cannot resolve {$expected}, it must be an instance, a class name or an array containing a class name and an array of parameters");
    }
}
