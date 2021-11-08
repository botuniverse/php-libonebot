<?php

namespace App;

use OneBot\V12\Action\ActionBase;
use OneBot\V12\Action\ActionResponse;
use OneBot\Console\Console;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\ActionObject;
use OneBot\V12\RetCode;
use OneBot\V12\Utils;

class ReplAction extends ActionBase
{
    public function onSendMessage(ActionObject $action): ActionResponse {
        Console::success(Utils::msgToString($action->params['message']));
        return ActionResponse::create($action->echo)->ok(['message_id' => mt_rand(0, 9999999)]);
    }
}