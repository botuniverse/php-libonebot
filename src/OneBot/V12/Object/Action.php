<?php

declare(strict_types=1);

namespace OneBot\V12\Object;

/**
 * OneBot 12 标准的 Action 请求对象
 */
class Action implements \JsonSerializable, \IteratorAggregate, \Stringable
{
    /** @var string 动作名称 */
    public string $action = '';

    /** @var array 动作参数 */
    public array $params = [];

    /** @var mixed 回包消息 */
    public $echo;

    public ?array $self;

    /**
     * 创建新的动作实例
     *
     * @param string $action 动作名称
     * @param array  $params 动作参数
     * @param mixed  $echo
     */
    public function __construct(string $action, array $params = [], $echo = null, ?array $self = null)
    {
        $this->action = $action;
        $this->params = $params;
        $this->echo = $echo;
        $this->self = $self;
    }

    public function __toString()
    {
        return json_encode($this->jsonSerialize(), JSON_UNESCAPED_SLASHES);
    }

    /**
     * 从数组创建动作实例
     */
    public static function fromArray(array $arr): Action
    {
        return new self($arr['action'], $arr['params'] ?? [], $arr['echo'] ?? null, $arr['self'] ?? null);
    }

    public function jsonSerialize(): array
    {
        $d = [
            'action' => $this->action,
            'params' => $this->params,
        ];
        if ($this->echo !== null) {
            $d['echo'] = $this->echo;
        }
        if ($this->self !== null) {
            $d['self'] = $this->self;
        }
        return $d;
    }

    /**
     * @noinspection PhpLanguageLevelInspection
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->jsonSerialize());
    }
}
