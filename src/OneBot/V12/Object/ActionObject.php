<?php

declare(strict_types=1);

namespace OneBot\V12\Object;

class ActionObject
{
    public $action = '';

    public $params = [];

    public $echo;

    public function __construct($action, $params = [], $echo = null)
    {
        $this->action = $action;
        $this->params = $params;
        $this->echo = $echo;
    }

    /**
     * @param array $arr
     *
     * @return ActionObject
     */
    public static function fromArray(array $arr): ActionObject
    {
        return new self($arr['action'], $arr['params'] ?? [], $arr['echo'] ?? null);
    }
}
