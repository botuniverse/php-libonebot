<?php

declare(strict_types=1);

namespace OneBot\V12\Config;

interface ConfigInterface
{
    public function get(string $key, $default = null);

    public function getEnabledCommunications(): array;
}
