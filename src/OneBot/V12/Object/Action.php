<?php

declare(strict_types=1);

namespace OneBot\V12\Object;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use ReturnTypeWillChange;

/**
 * OneBot 12 标准的 Action 请求对象
 */
class Action implements JsonSerializable, IteratorAggregate
{
    /** @var string 动作名称 */
    public string $action = '';

    /** @var array 动作参数 */
    public array $params = [];

    /** @var mixed 回包消息 */
    public $echo;

    /**
     * 创建新的动作实例
     *
     * @param string $action 动作名称
     * @param array  $params 动作参数
     * @param mixed  $echo
     */
    public function __construct(string $action, array $params = [], $echo = null)
    {
        $this->action = $action;
        $this->params = $params;
        $this->echo = $echo;
    }

    /**
     * 从数组创建动作实例
     */
    public static function fromArray(array $arr): Action
    {
        return new self($arr['action'], $arr['params'] ?? [], $arr['echo'] ?? null);
    }

    public function jsonSerialize(): array
    {
        if ($this->echo === null) {
            return [
                'action' => $this->action,
                'params' => $this->params,
            ];
        }
        return [
            'action' => $this->action,
            'params' => $this->params,
            'echo' => $this->echo,
        ];
    }

    /**
     * @noinspection PhpLanguageLevelInspection
     */
    #[ReturnTypeWillChange]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this);
    }
}
