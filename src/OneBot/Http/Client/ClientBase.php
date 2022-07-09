<?php

declare(strict_types=1);

namespace OneBot\Http\Client;

abstract class ClientBase
{
    /**
     * 设置 Client 的超时时间
     *
     * @param int $timeout 超时时间（毫秒）
     */
    abstract public function setTimeout(int $timeout);
}
