<?php

declare(strict_types=1);

namespace OneBot\V12\Action;

use OneBot\Util\Utils;
use OneBot\V12\Object\ActionObject;

/**
 * Demo REPL Action Handler.
 * Just for test.
 */
class ReplAction extends ActionBase
{
    public function onSendMessage(ActionObject $action): ActionResponse
    {
        ob_logger()->info(Utils::msgToString($action->params['message']));
        return ActionResponse::create($action->echo)->ok(['message_id' => mt_rand(0, 9999999)]);
    }
}
