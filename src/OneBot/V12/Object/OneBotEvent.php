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
            $this->message_segment_cache[] = new MessageSegment($segment['type'], $segment['data']);
        }
        return $this->message_segment_cache;
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
