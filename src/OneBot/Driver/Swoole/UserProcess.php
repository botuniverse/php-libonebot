<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole;

use OneBot\Driver\Interfaces\ProcessInterface;
use Swoole\Process;

class UserProcess extends Process implements ProcessInterface
{
    public function getPid(): int
    {
        return $this->pid;
    }
}
