<?php

namespace OneBot\V12\Action;

use OneBot\V12\RetCode;

class ActionResponse
{
    public $status = "ok";
    public $retcode = 0;
    public $data = [];
    public $message = "";

    public static function create($echo = null)
    {
        $a = new self();
        if ($echo !== null) {
            $a->echo = $echo;
        }
        return $a;
    }

    public function ok($data = []): ActionResponse
    {
        $this->status = "ok";
        $this->retcode = 0;
        $this->data = $data;
        $this->message = "";
        return $this;
    }

    public function fail($retcode, $message = ""): ActionResponse
    {
        $this->status = "failed";
        $this->retcode = $retcode;
        $this->data = [];
        $this->message = $message === "" ? RetCode::getMessage($retcode) : $message;
        return $this;
    }
}
