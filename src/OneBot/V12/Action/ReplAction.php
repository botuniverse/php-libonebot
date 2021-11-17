<?php

declare(strict_types=1);

namespace OneBot\V12\Action;

use OneBot\Logger\Console\ConsoleLogger;
use OneBot\V12\Object\ActionObject;
use OneBot\V12\Utils;

class ReplAction extends ActionBase
{
    public function onSendMessage(ActionObject $action): ActionResponse
    {
        // TODO: 改用通用logger
        ConsoleLogger::getInstance()->info(Utils::msgToString($action->params['message']));
        return ActionResponse::create($action->echo)->ok(['message_id' => mt_rand(0, 9999999)]);
    }
}
