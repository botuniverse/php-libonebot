<?php


namespace OneBot\V12;


class ActionResponse
{
    public $retcode = 0;
    public $data = [];
    public $message = "";

    public static function create($echo = null) {
        $a = new static();
        if ($echo !== null) $a->echo = $echo;
        return $a;
    }

    public function ok($data = []): ActionResponse {
        $this->retcode = 0;
        $this->data = $data;
        $this->message = "";
        return $this;
    }
}