<?php

declare(strict_types=1);

namespace OneBot\Driver;

use OneBot\Http\Client\StreamClient;
use OneBot\Http\Client\SwooleClient;
use OneBot\V12\Config\ConfigInterface;

abstract class Driver
{
    /** @var ConfigInterface */
    protected $config;

    protected $default_client_class;

    protected $alt_client_class;

    protected $_events = [];

    public function __construct($default_client_class = SwooleClient::class, $alt_client_class = StreamClient::class)
    {
        $this->default_client_class = $default_client_class;
        $this->alt_client_class = $alt_client_class;
    }

    public function getName(): string
    {
        return rtrim(strtolower(self::class), 'driver');
    }

    public function setConfig(ConfigInterface $config)
    {
        $this->config = $config;
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    abstract public function initDriverProtocols(array $comm);

    abstract public function run();
}
