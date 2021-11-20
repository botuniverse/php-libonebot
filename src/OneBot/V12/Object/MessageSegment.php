<?php

declare(strict_types=1);

namespace OneBot\V12\Object;

/**
 * OneBot 消息段
 */
class MessageSegment
{
    /**
     * 消息段名称
     *
     * @var string
     */
    public $type;

    /**
     * 消息段参数
     *
     * @var array
     */
    public $data;

    public function __construct(string $type, array $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    public static function createFromString(string $message): MessageSegment
    {
        return new self('text', ['text' => $message]);
    }
}
