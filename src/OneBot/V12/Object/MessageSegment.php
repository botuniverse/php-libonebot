<?php

declare(strict_types=1);

namespace OneBot\V12\Object;

use ReturnTypeWillChange;

class MessageSegment implements \JsonSerializable, \IteratorAggregate
{
    /** @var string 类型 */
    public string $type;

    /** @var array 数据 */
    public array $data;

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
    public static function text(string $message): MessageSegment
    {
        return new self('text', ['text' => $message]);
    }

    public static function mention(string $user_id): MessageSegment
    {
        return new self('mention', ['user_id' => $user_id]);
    }

    public static function mentionAll(): MessageSegment
    {
        return new self('mention_all', []);
    }

    public static function image(string $file_id): MessageSegment
    {
        return new self('image', ['file_id' => $file_id]);
    }

    public static function voice(string $file_id): MessageSegment
    {
        return new self('voice', ['file_id' => $file_id]);
    }

    public static function file(string $file_id): MessageSegment
    {
        return new self('file', ['file_id' => $file_id]);
    }

    public static function location($latitude, $longitude, string $title, string $content): MessageSegment
    {
        return new self('location', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'content' => $content,
        ]);
    }

    public static function reply(string $message_id, ?string $user_id = null): MessageSegment
    {
        $data = ['message_id' => $message_id];
        if ($user_id !== null) {
            $data['user_id'] = $user_id;
        }
        return new self('reply', $data);
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
        ];
    }

    /**
     * @noinspection PhpLanguageLevelInspection
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this);
    }
}
