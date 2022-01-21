<?php

declare(strict_types=1);

namespace OneBot\Driver;

use Exception;
use OneBot\Driver\Event\Event;
use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\HttpRequestEvent;
use OneBot\Driver\Workerman\Worker;
use OneBot\Http\HttpFactory;
use OneBot\Logger\Console\ExceptionHandler;
use OneBot\Util\MPUtils;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;

class WorkermanDriver extends Driver
{
    protected $http_worker;

    public function initDriverProtocols(array $comm)
    {
        $has_http = false;
        foreach ($comm as $k => $v) {
            if ($v['type'] == 'http') {
                $has_http = $k;
            }
        }
        if ($has_http !== false) {
            // 定义 Workerman 的 worker 和相关回调
            $this->http_worker = new Worker('http://' . $comm[$has_http]['host'] . ':' . $comm[$has_http]['port']);
            $this->http_worker->count = $comm[$has_http]['worker_count'] ?? 4;
            Worker::$internal_running = true; // 加上这句就可以不需要必须输 start 命令才能启动了，直接启动
            $this->initHttpServer();
            $this->http_worker->onWorkerStart = function (Worker $worker) {
                MPUtils::initProcess(ONEBOT_PROCESS_WORKER, $worker->id);
            };
        }
    }

    /**
     * @throws Exception
     */
    public function run()
    {
        Worker::runAll();
    }

    private function initHttpServer()
    {
        $this->http_worker->onMessage = function (TcpConnection $connection, Request $request) {
            ob_logger()->debug('Http request: ' . $request->uri());
            $event = new HttpRequestEvent(HttpFactory::getInstance()->createServerRequest(
                $request->method(),
                $request->uri(),
                $request->header(),
                $request->rawBody()
            ));
            $response = new WorkermanResponse();
            try {
                (new EventDispatcher(Event::EVENT_HTTP_REQUEST))->dispatch($event);
                if ($event->getResponse() !== null) {
                    $psr_response = $event->getResponse();
                    $response->withStatus($psr_response->getStatusCode());
                    $response->withHeaders($psr_response->getHeaders());
                    $response->withBody($psr_response->getBody());
                } else {
                    $response->withStatus(204);
                }
                $connection->send($response);
            } catch (Throwable $e) {
                ExceptionHandler::getInstance()->handle($e);
                $response->withStatus(500);
                $response->withBody('Internal Server Error');
                $connection->send($response);
            }
        };
    }
}
