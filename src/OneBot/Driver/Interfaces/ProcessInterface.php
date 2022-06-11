<?php

declare(strict_types=1);

namespace OneBot\Driver\Interfaces;

interface ProcessInterface
{
    public function getPid(): int;
}
