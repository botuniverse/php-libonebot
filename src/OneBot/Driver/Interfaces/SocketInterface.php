<?php

declare(strict_types=1);

namespace OneBot\Driver\Interfaces;

interface SocketInterface
{
    public const TYPE_WS = 1;

    public const TYPE_HTTP = 2;

    public const TYPE_HTTP_WEBHOOK = 3;

    public const TYPE_WS_REVERSE = 4;

    public function setConfig(array $config);

    public function getConfig(): array;
}
