<?php

declare(strict_types=1);

namespace OneBot\Http\WebSocket;

interface CloseFrameInterface extends FrameInterface
{
    public function getCode(): int;
}
