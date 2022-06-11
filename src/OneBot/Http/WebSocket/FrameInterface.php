<?php

declare(strict_types=1);

namespace OneBot\Http\WebSocket;

interface FrameInterface
{
    public function getData();

    public function getOpcode();

    public function isMasked(): bool;
}
