<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

trait SocketFlag
{
    /** @var int */
    protected $flag = 1;

    public function setFlag(int $flag): self
    {
        $this->flag = $flag;
        return $this;
    }

    public function getFlag(): int
    {
        return $this->flag;
    }
}
