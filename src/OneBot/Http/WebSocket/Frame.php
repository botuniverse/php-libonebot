<?php

declare(strict_types=1);

namespace OneBot\Http\WebSocket;

/**
 * psr-7 extended websocket frame
 */
class Frame implements FrameInterface
{
    /**
     * @var mixed|string
     */
    protected $data;

    /**
     * @var int The opcode of the frame
     */
    protected $opcode;

    /**
     * @var bool WebSocket Mask, RFC 6455 Section 10.3
     */
    protected $mask;

    public function __construct($data, int $opcode, bool $mask)
    {
        $this->data = $data;
        $this->opcode = $opcode;
        $this->mask = $mask;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getOpcode(): int
    {
        return $this->opcode;
    }

    /**
     * 规定当且仅当由客户端向服务端发送的 frame, 需要使用掩码覆盖
     */
    public function isMasked(): bool
    {
        return $this->mask;
    }
}
