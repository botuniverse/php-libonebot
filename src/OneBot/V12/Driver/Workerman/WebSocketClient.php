<?php

declare(strict_types=1);

namespace OneBot\V12\Driver\Workerman;

use OneBot\V12\Driver\WebSocketClientInterface;
use Psr\Http\Message\RequestInterface;

class WebSocketClient implements WebSocketClientInterface
{
    public function create(): bool
    {
        // TODO: Implement create() method.
        return false;
    }

    public function setMessageCallback(callable $callable): WebSocketClientInterface
    {
        // TODO: Implement setMessageCallback() method.
        return $this;
    }

    public function setCloseCallback(callable $callable): WebSocketClientInterface
    {
        // TODO: Implement setCloseCallback() method.
        return $this;
    }

    public function withRequest(RequestInterface $request): WebSocketClientInterface
    {
        // TODO: Implement withRequest() method.
        return $this;
    }

    public function push($data): bool
    {
        // TODO: Implement push() method.
        return false;
    }
}
