<?php

declare(strict_types=1);

namespace OneBot\V12\Object;

use OneBot\Util\Utils;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Validator;

/**
 * @method          getId()
 * @method          getType()
 * @method          getSelf()
 * @method          getDetailType()
 * @method          getSubType()
 * @method          getTime()
 * @method          getAltMessage()
 * @method          getGroupId()
 * @method          getUserId()
 * @method          getGuildId()
 * @method          getChannelId()
 * @method          getOperatorId()
 * @method          getMessageId()
 * @method          setId(string $id)
 * @method          setType(string $type)
 * @method          setSelf(array $self)
 * @method          setDetailType(string $detail_type)
 * @method          setSubType(string $sub_type)
 * @method          setTime(float|int $time)
 * @method          setAltMessage(string $alt_message)
 * @method          setGroupId(string $group_id)
 * @method          setUserId(string $user_id)
 * @method          setGuildId(string $guild_id)
 * @method          setChannelId(string $channel_id)
 * @method          setOperatorId(string $operator_id)
 * @method          setMessageId(string $message_id)
 * @method          setMessage(array|MessageSegment|string|\Stringable $message)
 * @property string $id
 * @property string $type
 * @property array  $self
 * @property string $detail_type
 * @property string $sub_type
 * @property int    $time
 */
class OneBotEvent implements \Stringable, \JsonSerializable
{
    private array $data;

    private ?array $message_segment_cache = null;

    /**
     * @throws OneBotException
     */
    public function __construct(array $data)
    {
        Validator::validateEventParams($data);
        $this->data = $data;
    }

    public function __call(string $name, array $args = [])
    {
        if (str_starts_with($name, 'get')) {
            $key = Utils::camelToSeparator(substr($name, 3));
            if (isset($this->data[$key])) {
                return $this->data[$key];
            }
            return null;
        }
        if (str_starts_with($name, 'set')) {
            if ($name === 'setMessage') {
                $this->message_segment_cache = null;
            }
            $key = Utils::camelToSeparator(substr($name, 3));
            if (isset($this->data[$key])) {
                $this->data[$key] = $args[0];
                return true;
            }
            return false;
        }
        throw new \BadMethodCallException('Call to undefined method ' . __CLASS__ . '::' . $name . '()');
    }

    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    public function __toString(): string
    {
        return json_encode($this->data, JSON_UNESCAPED_SLASHES);
    }

    /**
     * 获取事件的扩展字段
     *
     * @param  string     $key 键名
     * @return null|mixed
     */
    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * 获取 OneBot 事件的原数据数组
     */
    public function getRawData(): array
    {
        return $this->data;
    }

    /**
     * 获取消息段数组
     * 当事件不是消息时，返回 null
     *
     * @param  bool                        $return_assoc_array 是否返回数组形式的消息段，默认为false，返回对象形式的消息段
     * @return null|array|MessageSegment[]
     */
    public function getMessage(bool $return_assoc_array = false): ?array
    {
        if (!isset($this->data['message'])) {
            return null;
        }
        if ($return_assoc_array) {
            return $this->data['message'];
        }
        if ($this->message_segment_cache !== null) {
            return $this->message_segment_cache;
        }
        $this->message_segment_cache = [];
        foreach ($this->data['message'] as $segment) {
            $this->message_segment_cache[] = $segment instanceof MessageSegment ? $segment : new MessageSegment($segment['type'], $segment['data']);
        }
        return $this->message_segment_cache;
    }

    /**
     * 获取纯文本消息
     */
    public function getMessageString(): string
    {
        $message = $this->getMessage();
        if ($message === null) {
            return '';
        }
        $message_string = '';
        foreach ($message as $segment) {
            if ($segment->type === 'text') {
                $message_string .= $segment->data['text'];
            } else {
                $message_string .= '[富文本:' . $segment->type . ']';
            }
        }
        return $message_string;
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
