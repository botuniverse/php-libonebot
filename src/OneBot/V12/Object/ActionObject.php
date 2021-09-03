<?php


namespace OneBot\V12\Object;


class ActionObject
{
    public $action = "";
    public $params = [];
    public $echo = null;

    public function __construct($action, $params = [], $echo = null) {
        $this->action = $action;
        $this->params = $params;
        $this->echo = $echo;
    }

    /**
     * @param $arr
     * @return ActionObject
     */
    public static function fromArray($arr): ActionObject {
        return new static($arr["action"], $arr["params"] ?? [], $arr["echo"] ?? null);
    }
}