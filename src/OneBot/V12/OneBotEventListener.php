<?php

declare(strict_types=1);

namespace OneBot\V12;

use MessagePack\Exception\UnpackingFailedException;
use MessagePack\MessagePack;
use OneBot\Driver\Event\DriverInitEvent;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Event\WebSocket\WebSocketMessageEvent;
use OneBot\Driver\Event\WebSocket\WebSocketOpenEvent;
use OneBot\Driver\Interfaces\WebSocketClientInterface;
use OneBot\Driver\Process\ProcessManager;
use OneBot\Http\Client\Exception\NetworkException;
use OneBot\Http\HttpFactory;
use OneBot\Http\WebSocket\CloseFrameInterface;
use OneBot\Http\WebSocket\FrameInterface;
use OneBot\Http\WebSocket\Opcode;
use OneBot\Util\Singleton;
use OneBot\Util\Utils;
use OneBot\V12\Action\ActionResponse;
use OneBot\V12\Action\DefaultActionHandler;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\Action;
use Throwable;

/**
 * OneBot 相关事件监听的集合
 */
class OneBotEventListener
{
    use Singleton;

    /**
     * OneBot 相关的 HTTP 请求处理
     *
     * 先排除 Chrome 自动请求的 icon，直接返回 404；
     * 然后对传入类型解析，分别交给 json 和 msgpack
     */
    public function onHttpRequest(HttpRequestEvent $event): void
    {
        if ($event->getSocketFlag() !== 1) {
            return;
        }
        try {
            $request = $event->getRequest();
            // OneBot 12 只接受 POST 请求
            if ($request->getMethod() !== 'POST') {
                $event->withResponse(HttpFactory::getInstance()->createResponse(405, 'Not Allowed'));
                return;
            }
            // OneBot 12 鉴权部分
            if (($stored_token = $event->getSocketConfig()['access_token'] ?? '') !== '') {
                $token = $request->getHeaderLine('Authorization');
                $token = explode('Bearer ', $token);
                if (!isset($token[1]) || $token[1] !== $stored_token) { // 没有 token，鉴权失败
                    $event->withResponse(HttpFactory::getInstance()->createResponse(401, 'Unauthorized'));
                    return;
                }
            }
            if ($request->getHeaderLine('content-type') === 'application/json') {
                $response_obj = $this->processActionRequest($request->getBody());
                $response = HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/json'], json_encode($response_obj, JSON_UNESCAPED_UNICODE));
                $event->withResponse($response);
            } elseif ($request->getHeaderLine('content-type') === 'application/msgpack') {
                $response_obj = $this->processActionRequest($request->getBody()->getContents(), ONEBOT_MSGPACK);
                $response = HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/msgpack'], MessagePack::pack((array) $response_obj));
                $event->withResponse($response);
            } else {
                $event->withResponse(HttpFactory::getInstance()->createResponse(415, 'Unsupported Media Type'));
                return;
            }
        } catch (OneBotFailureException $e) {
            $response_obj = ActionResponse::create($e->getActionObject()->echo ?? null)->fail($e->getRetCode());
            $response = HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/json'], json_encode($response_obj, JSON_UNESCAPED_UNICODE));
            $event->withResponse($response);
            ob_logger()->warning('OneBot Failure: ' . RetCode::getMessage($e->getRetCode()) . '(' . $e->getRetCode() . ') at ' . $e->getFile() . ':' . $e->getLine());
        } catch (Throwable $e) {
            $response_obj = ActionResponse::create($response_obj->echo ?? null)->fail(RetCode::INTERNAL_HANDLER_ERROR);
            $response = HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/json'], json_encode($response_obj, JSON_UNESCAPED_UNICODE));
            $event->withResponse($response);
            ob_logger()->error('Unhandled ' . get_class($e) . ': ' . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString());
        }
    }

    /**
     * OneBot 相关的 WebSocket 连接处理（仅限正向 WS）
     */
    public function onWebSocketOpen(WebSocketOpenEvent $event): void
    {
        // TODO: WebSocket 接入后的认证操作
        if ($event->getSocketFlag() !== 1) {
            return;
        }
        $request = $event->getRequest();
        // OneBot 12 鉴权部分
        if (($stored_token = $event->getSocketConfig()['access_token'] ?? '') !== '') {
            $token = $request->getHeaderLine('Authorization');
            $token = explode('Bearer ', $token);
            if (!isset($token[1]) || $token[1] !== $stored_token) { // 没有 token，鉴权失败
                $event->withResponse(HttpFactory::getInstance()->createResponse(401, 'Unauthorized'));
                return;
            }
        }
    }

    /**
     * OneBot 相关的 WebSocket 消息处理（正反 WS 都共用这里）
     */
    public function onWebSocketMessage(WebSocketMessageEvent $event): void
    {
        if ($event->getSocketFlag() !== 1) {
            return;
        }
        try {
            // 通过对 Frame 的 Opcode 进行判断，是否为 msgpack 数据，如果是文本的话，一律当 JSON 解析，如果是二进制，一律当 msgpack 解析
            $response_obj = $this->processActionRequest($event->getFrame()->getData(), $event->getFrame()->getOpcode() === Opcode::BINARY ? ONEBOT_MSGPACK : ONEBOT_JSON);
            $event->send(json_encode($response_obj));
        } catch (OneBotFailureException $e) {
            $response_obj = ActionResponse::create($e->getActionObject()->echo ?? null)->fail($e->getRetCode());
            $event->send(json_encode($response_obj));
            ob_logger()->warning('OneBot Failure: ' . RetCode::getMessage($e->getRetCode()) . '(' . $e->getRetCode() . ') at ' . $e->getFile() . ':' . $e->getLine());
        } catch (Throwable $e) {
            $response_obj = ActionResponse::create()->fail(RetCode::INTERNAL_HANDLER_ERROR);
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
        ob_logger_register(ob_logger());
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
        ob_logger_register(ob_logger());
    }

    /**
     * OneBot 相关的 Manager 进程退出后的逻辑处理
     */
    public function onManagerStop(): void
    {
        ob_logger()->debug('Manager stopped');
    }

    /**
     * OneBot 相关的 DriverInit 初始化函数
     *
     * 这里主要的部分还是用于初始化 ws_reverse 连接用的。
     */
    public function onDriverInit(DriverInitEvent $event)
    {
        // 将 ws reverse 请求的 HTTP Client 初始化先
        $event->getDriver()->initWSReverseClients(OneBot::getInstance()->getRequestHeaders(['Sec-WebSocket-Protocol' => '12.' . OneBot::getInstance()->getImplementName()]));
        foreach ($event->getDriver()->getWSReverseSockets() as $v) {
            // 根据 Socket 内的信息生成 HTTP Request 对象
            $request = HttpFactory::getInstance()->createRequest('GET', $v->getUrl(), $v->getHeaders());
            if (isset($v->getConfig()['access_token'])) {
                // 鉴权
                $request = $request->withAddedHeader('Authorization', 'Bearer ' . $v->getConfig()['access_token']);
            }
            $v->getClient()->withRequest($request);
            ob_logger()->info('初始化 ws reverse 连接 ing');
            // 下面就是一堆重连的东西了
            $reconnect = function () use ($v, $event, &$reconnect) {
                try {
                    if ($v->getClient()->reconnect() !== true) {
                        ob_logger()->error('ws_reverse_client连接失败：无法建立连接');
                        $event->getDriver()->getEventLoop()->addTimer($v->getReconnectInterval(), $reconnect);
                    }
                } catch (NetworkException $e) {
                    ob_logger()->error('ws_reverse_client连接失败：' . $e->getMessage());
                    $event->getDriver()->getEventLoop()->addTimer($v->getReconnectInterval(), $reconnect);
                }
            };
            $v->getClient()->setMessageCallback([$this, 'onClientMessage']);
            $v->getClient()->setCloseCallback(function () use ($event, $reconnect, $v) {
                ob_logger()->error('WS Reverse 服务端断开连接！');
                $event->getDriver()->getEventLoop()->addTimer($v->getReconnectInterval(), $reconnect);
            });
            try {
                if ($v->getClient()->connect() !== true) {
                    ob_logger()->error('ws_reverse_client连接失败：首次无法建立连接');
                    $event->getDriver()->getEventLoop()->addTimer($v->getReconnectInterval(), $reconnect);
                }
            } catch (NetworkException $e) {
                ob_logger()->error('ws_reverse_client连接失败：' . $e->getMessage());
                $event->getDriver()->getEventLoop()->addTimer($v->getReconnectInterval(), $reconnect);
            }
        }
    }

    /**
     * 此方法目前只用于在 First Worker 激活 DriverInitEvent 用
     *
     * 虽然此方法会在每个 Worker 执行，但内部限制到 #0 运行。
     */
    public function onFirstWorkerInit()
    {
        if (ProcessManager::getProcessId() === 0) {
            ob_event_dispatcher()->dispatchWithHandler(new DriverInitEvent(OneBot::getInstance()->getDriver()));
        }
    }

    /**
     * 此方法的作用是将 ws_reverse，也就是本地发起的 ws client，处理收到的消息事件，转换并再次分发为 WebSocketMessageEvent。
     *
     * 也就是说，此处就是一个中转，最终分发了标准的 WS 消息事件，就是相当于调用此类上方的 onWebSocketMessage。
     *
     * @param FrameInterface           $frame  Frame 对象
     * @param WebSocketClientInterface $client WebSocketClient 对象实例
     */
    public function onClientMessage(FrameInterface $frame, WebSocketClientInterface $client)
    {
        $event = new WebSocketMessageEvent($client->getFd(), $frame, function (int $fd, $data) use ($client) {
            if ($data instanceof FrameInterface) {
                return $client->push($data->getData());
            }
            return $client->push($data);
        });
        ob_event_dispatcher()->dispatchWithHandler($event);
    }

    /**
     * 此方法的作用是将 ws client 关闭事件，转换并再次分发为 WebSocketCloseEvent
     *
     * @param CloseFrameInterface      $frame  CloseFrame 对象
     * @param WebSocketClientInterface $client WebSocketClient 对象实例
     * @param mixed                    $status
     */
    public function onClientClose(CloseFrameInterface $frame, WebSocketClientInterface $client, $status)
    {
        ob_logger()->error('断开连接！' . $status);
    }

    /**
     * 调用 ActionHandler 已经实现了的动作，并将返回值返回到上层
     *
     * @param  mixed|string                                     $raw_data 传入的实际数据包，这里还是仅可传入 json 或 msgpack
     * @throws Exception\OneBotException|OneBotFailureException 抛出 OneBot 异常，统一异常的 JSON 回复
     * @internal
     */
    public function processActionRequest($raw_data, int $type = ONEBOT_JSON): ActionResponse
    {
        switch ($type) {
            case ONEBOT_JSON:
                $json = json_decode((string) $raw_data, true);
                if (!isset($json['action'])) {
                    throw new OneBotFailureException(RetCode::BAD_REQUEST);
                }
                $action_obj = new Action($json['action'], $json['params'] ?? [], $json['echo'] ?? null);
                break;
            case ONEBOT_MSGPACK:
                try {
                    $msgpack = MessagePack::unpack($raw_data);
                    if (!isset($msgpack['action'])) {
                        throw new OneBotFailureException(RetCode::BAD_REQUEST);
                    }
                    $action_obj = Action::fromArray($msgpack);
                } catch (UnpackingFailedException $e) {
                    throw new OneBotFailureException(RetCode::BAD_REQUEST);
                }
                break;
            default:
                throw new OneBotFailureException(RetCode::INTERNAL_HANDLER_ERROR);
        }

        try {
            if (($handler = OneBot::getInstance()->getActionHandler($action_obj->action)) !== null) {
                $response_obj = call_user_func($handler[0], $action_obj, $type);
            } else {
                // 解析调用action handler
                $base_handler = OneBot::getInstance()->getBaseActionHandler();
                if ($base_handler === null) {
                    $base_handler = OneBot::getInstance()->setActionHandlerClass(DefaultActionHandler::class)->getBaseActionHandler();
                }
                $action_call_func = Utils::getActionFuncName($base_handler, $action_obj->action);
                $response_obj = $base_handler->{$action_call_func}($action_obj, $type);
            }
        } catch (OneBotFailureException $e) {
            $response_obj = ActionResponse::create($e->getActionObject()->echo ?? null)->fail($e->getRetCode());
        }
        return $response_obj instanceof ActionResponse ? $response_obj : ActionResponse::create($action_obj->echo)->fail(RetCode::BAD_HANDLER);
    }
}
