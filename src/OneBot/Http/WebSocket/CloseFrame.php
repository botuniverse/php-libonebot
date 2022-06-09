<?php

declare(strict_types=1);

namespace OneBot\Http\WebSocket;

class CloseFrame extends Frame implements CloseFrameInterface
{
    /**
     * @var int
     */
    protected $code;

    /**
     * @var string (UTF-8)
     */
    protected $reason;

    public function __construct($data, int $opcode, bool $mask, int $code, string $reason = '')
    {
        parent::__construct($data, $opcode, $mask);

        $this->code = $code;
        $this->reason = $reason;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
