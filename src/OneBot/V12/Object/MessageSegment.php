<?php

declare(strict_types=1);

namespace OneBot\V12\Object;

class MessageSegment
{
    /** @var string 类型 */
    public $type;

    /** @var array 数据 */
    public $data;

    /**
     * 创建新的消息段
     *
     * @param string $type 类型
     * @param array  $data 数据
     */
    public function __construct(string $type, array $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * 根据字符串创建文本消息段
     *
     * @param string $message 消息
     */
    public static function createFromString(string $message): MessageSegment
    {
        return new self('text', ['text' => $message]);
    }
}
