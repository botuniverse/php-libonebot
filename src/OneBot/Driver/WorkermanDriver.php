<?php

declare(strict_types=1);

namespace OneBot\Driver;

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
    /** @var Worker HTTP Worker */
    protected $http_worker;

    /**
     * {@inheritDoc}
     */
    public function initDriverProtocols(array $comm): void
    {
        $http_index = null;
        foreach ($comm as $k => $v) {
            if ($v['type'] === 'http') {
                $http_index = $k;
            }
        }
        if ($http_index !== null) {
            // 定义 Workerman 的 worker 和相关回调
            $this->http_worker = new Worker('http://' . $comm[$http_index]['host'] . ':' . $comm[$http_index]['port']);
            $this->http_worker->count = $comm[$http_index]['worker_count'] ?? 4;
            Worker::$internal_running = true; // 加上这句就可以不需要必须输 start 命令才能启动了，直接启动
            $this->initHttpServer();
            $this->http_worker->onWorkerStart = static function (Worker $worker) {
                MPUtils::initProcess(ONEBOT_PROCESS_WORKER, $worker->id);
            };
        }
    }

    /**
     * {@inheritDoc}
     */
    public function run(): void
    {
        try {
            Worker::runAll();
        } catch (Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
        }
    }

    /**
     * 初始化 HTTP 服务端
     */
    private function initHttpServer(): void
    {
        $this->http_worker->onMessage = static function (TcpConnection $connection, Request $request) {
            ob_logger()->debug('Http request: ' . $request->uri());
            $event = new HttpRequestEvent(HttpFactory::getInstance()->createServerRequest(
                $request->method(),
                $request->uri(),
                $request->header(),
                $request->rawBody()
            ));
            $response = new WorkermanResponse();
            try {
                (new EventDispatcher())->dispatch($event);
                if (($psr_response = $event->getResponse()) !== null) {
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
