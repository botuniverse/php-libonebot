<?php

declare(strict_types=1);

namespace OneBot\V12;

use MessagePack\Exception\UnpackingFailedException;
use MessagePack\MessagePack;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Event\WebSocket\WebSocketMessageEvent;
use OneBot\Driver\Event\WebSocket\WebSocketOpenEvent;
use OneBot\Driver\ProcessManager;
use OneBot\Http\HttpFactory;
use OneBot\Util\Singleton;
use OneBot\Util\Utils;
use OneBot\V12\Action\ActionResponse;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\ActionObject;
use Throwable;

class OneBotEventListener
{
    use Singleton;

    /**
     * OneBot 相关的 HTTP 请求处理
     */
    public function onHttpRequest(HttpRequestEvent $event): void
    {
        try {
            $request = $event->getRequest();
            // 排除掉 Chrome 浏览器的多余请求
            if ($request->getUri() == '/favicon.ico') {
                $event->withResponse(HttpFactory::getInstance()->createResponse(404));
                return;
            }
            if ($request->getHeaderLine('content-type') === 'application/json') {
                $response_obj = $this->processActionRequest($request->getBody());
                $response = HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/json'], json_encode($response_obj, JSON_UNESCAPED_UNICODE));
                $event->withResponse($response);
            } elseif ($request->getHeaderLine('content-type') === 'application/msgpack') {
                $response_obj = $this->processActionRequest($request->getBody());
                $response = HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/msgpack'], MessagePack::pack($response_obj));
                $event->withResponse($response);
            } else {
                throw new OneBotFailureException(RetCode::BAD_REQUEST);
            }
        } catch (OneBotFailureException $e) {
            $response_obj = ActionResponse::create($e->getActionObject()->echo ?? null)->fail($e->getRetCode());
            $response = HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/json'], json_encode($response_obj, JSON_UNESCAPED_UNICODE));
            $event->withResponse($response);
            ob_logger()->warning('OneBot Failure: ' . RetCode::getMessage($e->getRetCode()) . '(' . $e->getRetCode() . ') at ' . $e->getFile() . ':' . $e->getLine());
        } catch (Throwable $e) {
            $response_obj = ActionResponse::create($action_obj->echo ?? null)->fail(RetCode::INTERNAL_HANDLER_ERROR);
            $response = HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/json'], json_encode($response_obj, JSON_UNESCAPED_UNICODE));
            $event->withResponse($response);
            ob_logger()->error('Unhandled ' . get_class($e) . ': ' . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString());
        }
    }

    /**
     * OneBot 相关的 WebSocket 连接处理
     */
    public function onWebSocketOpen(WebSocketOpenEvent $event): void
    {
        // TODO: WebSocket 接入后的认证操作
    }

    /**
     * OneBot 相关的 WebSocket 消息处理
     */
    public function onWebSocketMessage(WebSocketMessageEvent $event): void
    {
        try {
            $response_obj = $this->processActionRequest($event->getData());
            $event->send(json_encode($response_obj));
        } catch (OneBotFailureException $e) {
            $response_obj = ActionResponse::create($e->getActionObject()->echo ?? null)->fail($e->getRetCode());
            $event->send(json_encode($response_obj));
            ob_logger()->warning('OneBot Failure: ' . RetCode::getMessage($e->getRetCode()) . '(' . $e->getRetCode() . ') at ' . $e->getFile() . ':' . $e->getLine());
        } catch (Throwable $e) {
            $response_obj = ActionResponse::create($action_obj->echo ?? null)->fail(RetCode::INTERNAL_HANDLER_ERROR);
            $event->send(json_encode($response_obj));
            ob_logger()->error('Unhandled ' . get_class($e) . ': ' . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString());
        }
    }

    /**
     * OneBot 相关的 Worker 进程启动后逻辑的处理
     */
    public function onWorkerStart(): void
    {
        ob_logger()->debug('Worker #' . ProcessManager::getProcessId() . ' started');
    }

    /**
     * OneBot 相关的 Worker 进程退出后逻辑的处理
     */
    public function onWorkerStop(): void
    {
        ob_logger()->debug('Worker #' . ProcessManager::getProcessId() . ' stopped');
    }

    /**
     * OneBot 相关的 Manager 进程启动后的逻辑处理
     */
    public function onManagerStart(): void
    {
        ob_logger()->debug('Manager started');
    }

    /**
     * OneBot 相关的 Manager 进程退出后的逻辑处理
     */
    public function onManagerStop(): void
    {
        ob_logger()->debug('Manager stopped');
    }

    /**
     * @param  mixed                  $raw_data
     * @throws OneBotFailureException
     */
    private function processActionRequest($raw_data, int $type = ONEBOT_JSON): ActionResponse
    {
        switch ($type) {
            case ONEBOT_JSON:
                $json = json_decode((string) $raw_data, true);
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
        if ($action_handler === null) {
            throw new OneBotFailureException(RetCode::INTERNAL_HANDLER_ERROR, $action_obj, '动作处理器不存在');
        }
        $action_call_func = Utils::getActionFuncName($action_handler, $action_obj->action);
        $response_obj = $action_handler->{$action_call_func}($action_obj);
        return $response_obj instanceof ActionResponse ? $response_obj : ActionResponse::create($action_obj->echo)->fail(RetCode::BAD_HANDLER);
    }
}
