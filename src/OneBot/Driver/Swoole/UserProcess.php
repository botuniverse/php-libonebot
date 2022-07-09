<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole;

use OneBot\Driver\Interfaces\ProcessInterface;
use Swoole\Process;

class UserProcess extends Process implements ProcessInterface
{
    public function getPid(): int
    {
        // TODO: 完成 Swoole 的自定义进程模型
        return $this->pid;
    }
}
