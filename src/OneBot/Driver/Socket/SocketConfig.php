<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

trait SocketConfig
{
    protected array $config = [];

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config)
    {
        $this->config = $config;
    }
}
