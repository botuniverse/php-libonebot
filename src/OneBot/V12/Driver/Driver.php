<?php

declare(strict_types=1);

namespace OneBot\V12\Driver;

use MessagePack\Exception\UnpackingFailedException;
use MessagePack\MessagePack;
use OneBot\V12\Action\ActionResponse;
use OneBot\V12\Config\ConfigInterface;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\ActionObject;
use OneBot\V12\Object\Event\OneBotEvent;
use OneBot\V12\OneBot;
use OneBot\V12\RetCode;
use OneBot\V12\Utils;

abstract class Driver
{
    /** @var ConfigInterface */
    protected $config;

    public function getName(): string
    {
        return rtrim(strtolower(self::class), 'driver');
    }

    public function setConfig(ConfigInterface $config)
    {
        $this->config = $config;
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    abstract public function emitOBEvent(OneBotEvent $event);

    abstract public function initComm();

    abstract public function run();

    /**
     * @param $raw_data
     * @throws OneBotFailureException
     */
    protected function emitHttpRequest($raw_data, int $type = ONEBOT_JSON): ActionResponse
    {
        switch ($type) {
            case ONEBOT_JSON:
                $json = json_decode($raw_data, true);
                if (!isset($json['action'])) {
                    throw new OneBotFailureException(RetCode::BAD_REQUEST);
                }
                $action_obj = new ActionObject($json['action'], $json['params'] ?? [], $json['echo'] ?? null);
                break;
            case ONEBOT_MSGPACK:
                try {
                    $msgpack = MessagePack::unpack($raw_data);
                    if (!isset($msgpack['action'])) {
                        throw new OneBotFailureException(RetCode::BAD_REQUEST);
                    }
                    $action_obj = $msgpack;
                } catch (UnpackingFailedException $e) {
                    throw new OneBotFailureException(RetCode::BAD_REQUEST);
                }
                break;
            default:
                throw new OneBotFailureException(RetCode::INTERNAL_HANDLER_ERROR);
        }
        // 解析调用action handler
        $action_handler = OneBot::getInstance()->getActionHandler();
        $action_call_func = Utils::getActionFuncName($action_handler, $action_obj->action);
        $response_obj = $action_handler->{$action_call_func}($action_obj);
        return $response_obj instanceof ActionResponse ? $response_obj : ActionResponse::create($action_obj->echo)->fail(RetCode::BAD_HANDLER);
    }
}
