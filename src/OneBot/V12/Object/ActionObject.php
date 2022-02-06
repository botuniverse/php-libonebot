<?php

declare(strict_types=1);

namespace OneBot\V12\Object;

class ActionObject
{
    /** @var string 动作名称 */
    public $action = '';

    /** @var array 动作参数 */
    public $params = [];

    public $echo;

    /**
     * 创建新的动作实例
     *
     * @param string $action 动作名称
     * @param array  $params 动作参数
     * @param null   $echo
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
    public static function fromArray(array $arr): ActionObject
    {
        return new self($arr['action'], $arr['params'] ?? [], $arr['echo'] ?? null);
    }
}
