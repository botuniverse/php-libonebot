<?php

declare(strict_types=1);

namespace OneBot\V12\Driver;

use Error;
use MessagePack\MessagePack;
use OneBot\V12\Action\ActionResponse;
use OneBot\V12\Driver\Workerman\Worker;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\MPUtils;
use OneBot\V12\Object\Event\OneBotEvent;
use OneBot\V12\RetCode;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class WorkermanDriver extends Driver
{
    protected $http_worker;

    public function emitOBEvent(OneBotEvent $event): bool
    {
        return false;
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
            $this->http_worker->onWorkerStart = function (Worker $worker) {
                MPUtils::initProcess(ONEBOT_PROCESS_WORKER, $worker->id);
            };
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
            if ($request->header('content-type') === 'application/json') {
                $response_obj = $this->emitHttpRequest($request->rawBody());
                $response = new Response();
                $response->withHeader('content-type', 'application/json');
                $response->withBody(json_encode($response_obj, JSON_UNESCAPED_UNICODE));
                $connection->send($response);
            } elseif ($request->header('content-type') === 'application/msgpack') {
                $response_obj = $this->emitHttpRequest($request->rawBody(), ONEBOT_MSGPACK);
                $response = new Response();
                $response->withHeader('content-type', 'application/msgpack');
                $response->withBody(MessagePack::pack($response_obj));
                $connection->send($response);
            } else {
                throw new OneBotFailureException(RetCode::BAD_REQUEST);
            }
        } catch (OneBotFailureException $e) {
            $response_obj = ActionResponse::create($e->getActionObject()->echo ?? null)->fail($e->getRetCode());
            $this->sendWithBody($connection, ONEBOT_JSON, $response_obj);
            ob_logger()->warning('OneBot Failure: ' . RetCode::getMessage($e->getRetCode()) . '(' . $e->getRetCode() . ') at ' . $e->getFile() . ':' . $e->getLine());
        } catch (Throwable|Error $e) {
            $response_obj = ActionResponse::create($action_obj->echo ?? null)->fail(RetCode::INTERNAL_HANDLER_ERROR);
            $this->sendWithBody($connection, ONEBOT_JSON, $response_obj);
            ob_logger()->error('Unhandled ' . get_class($e) . ': ' . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString());
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
