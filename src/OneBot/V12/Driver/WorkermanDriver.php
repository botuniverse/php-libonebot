<?php

declare(strict_types=1);

namespace OneBot\V12\Driver;

use Error;
use MessagePack\Exception\UnpackingFailedException;
use MessagePack\MessagePack;
use OneBot\Console\Console;
use OneBot\V12\Action\ActionResponse;
use OneBot\V12\Driver\Config\Config;
use OneBot\V12\Driver\Workerman\Worker;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\ActionObject;
use OneBot\V12\Object\EventObject;
use OneBot\V12\OneBot;
use OneBot\V12\RetCode;
use OneBot\V12\Utils;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class WorkermanDriver implements Driver
{
    /**
     * @var Config
     */
    private $config;

    private $http_worker;

    public function getName(): string
    {
        return 'workerman';
    }

    public function setConfig(Config $config)
    {
        $this->config = $config;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function emitOBEvent(EventObject $event)
    {
    }

    public function initComm()
    {
        $enabled_com = $this->config->getEnabledCommunications();
        $has_http = false;
        foreach ($enabled_com as $k => $v) {
            if ($v['type'] == 'http') {
                $has_http = $k;
            }
        }
        if ($has_http !== false) {
            // 定义 Workerman 的 worker 和相关回调
            $this->http_worker = new Worker('http://' . $enabled_com[$has_http]['host'] . ':' . $enabled_com[$has_http]['port']);
            $this->http_worker->count = $enabled_com[$has_http]['worker_count'] ?? 4;
            Worker::$internal_running = true; // 加上这句就可以不需要必须输 start 命令才能启动了，直接启动
            $this->http_worker->onMessage = [$this, 'onHttpMessage'];
        }
    }

    public function run()
    {
        Worker::runAll();
    }

    /**
     * @internal
     */
    public function onHttpMessage(TcpConnection $connection, ?Request $request)
    {
        try {
            // 区分msgpack和json格式
            if ($request->header('content-type') === 'application/json') {
                $raw_type = ONEBOT_JSON;
                $obj = $request->rawBody();
                $json = json_decode($obj, true);
                if (!isset($json['action'])) {
                    throw new OneBotFailureException(RetCode::BAD_REQUEST);
                }
                $action_obj = new ActionObject($json['action'], $json['params'] ?? [], $json['echo'] ?? null);
            } elseif (($request->header['content-type'] ?? null) === 'application/msgpack') {
                $raw_type = ONEBOT_MSGPACK;
                $obj = $request->rawBody();
                try {
                    $msgpack = MessagePack::unpack($obj);
                } catch (UnpackingFailedException $e) {
                    throw new OneBotFailureException(RetCode::BAD_REQUEST);
                }
                if (!isset($msgpack['action'])) {
                    throw new OneBotFailureException(RetCode::BAD_REQUEST);
                }
                $action_obj = $msgpack;
            } else {
                // 两者都不是的话，直接报错
                throw new OneBotFailureException(RetCode::BAD_REQUEST);
            }

            // 解析调用action handler
            $action_handler = OneBot::getInstance()->getActionHandler();
            $action_call_func = Utils::getActionFuncName($action_handler, $action_obj->action);
            $response_obj = $action_handler->{$action_call_func}($action_obj);
            if ($response_obj instanceof ActionResponse) {
                $this->sendWithBody($connection, $raw_type, $response_obj);
            } else {
                $fail_response = ActionResponse::create($action_obj->echo)->fail(RetCode::BAD_HANDLER);
                $this->sendWithBody($connection, $raw_type, $fail_response);
            }
        } catch (OneBotFailureException $e) {
            $response_obj = ActionResponse::create($e->getActionObject()->echo ?? null)->fail($e->getRetCode());
            $this->sendWithBody($connection, ONEBOT_JSON, $response_obj);
            Console::warning('OneBot Failure: ' . RetCode::getMessage($e->getRetCode()) . '(' . $e->getRetCode() . ') at ' . $e->getFile() . ':' . $e->getLine());
        } catch (Throwable|Error $e) {
            $response_obj = ActionResponse::create($action_obj->echo ?? null)->fail(RetCode::INTERNAL_HANDLER_ERROR);
            $this->sendWithBody($connection, ONEBOT_JSON, $response_obj);
            Console::error('Unhandled ' . get_class($e) . ': ' . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString());
        }
    }

    /**
     * @param $connection
     * @param $raw_type
     * @param $body
     *
     * @internal
     */
    private function sendWithBody($connection, $raw_type, $body)
    {
        $response = new Response();
        switch ($raw_type) {
            case ONEBOT_JSON:
                $response->withHeader('content-type', 'application/json');
                $response->withBody(json_encode($body, JSON_UNESCAPED_UNICODE));
                $connection->send($response);
                break;
            case ONEBOT_MSGPACK:
                $response->withHeader('content-type', 'application/msgpack');
                $response->withBody(MessagePack::pack($body));
                $connection->send($response);
                break;
        }
    }
}
