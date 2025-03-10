<?php

declare(strict_types=1);

namespace OneBot\V12\Exception;

use OneBot\V12\Object\Action;
use OneBot\V12\RetCode;

class OneBotFailureException extends OneBotException
{
    private $retcode;

    /**
     * @var null|Action
     */
    private $action_object;

    public function __construct(
        $retcode = RetCode::INTERNAL_HANDLER_ERROR,
        ?Action $action_object = null,
        $message = null,
        ?\Exception $previous = null
    ) {
        $this->retcode = $retcode;
        $this->action_object = $action_object;
        $message = $message ?? RetCode::getMessage($retcode);
        parent::__construct($message, 0, $previous);
    }

    public function getRetCode()
    {
        return $this->retcode;
    }

    public function getActionObject(): ?Action
    {
        return $this->action_object;
    }
}
