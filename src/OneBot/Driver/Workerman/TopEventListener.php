<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use Choir\Http\HttpFactory;
use Choir\WebSocket\FrameFactory;
use Choir\WebSocket\FrameInterface;
use OneBot\Driver\Coroutine\Adaptive;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Event\Process\WorkerStartEvent;
use OneBot\Driver\Event\Process\WorkerStopEvent;
use OneBot\Driver\Event\WebSocket\WebSocketCloseEvent;
use OneBot\Driver\Event\WebSocket\WebSocketMessageEvent;
use OneBot\Driver\Event\WebSocket\WebSocketOpenEvent;
use OneBot\Driver\Process\ProcessManager;
use OneBot\Exception\ExceptionHandler;
use OneBot\Util\Singleton;
use OneBot\Util\Utils;
use Psr\Http\Message\ResponseInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;

class TopEventListener
{
    use Singleton;

    /**
     * Workerman 的顶层 workerStart 事件回调
     */
    public function onWorkerStart(Worker $worker)
    {
        ProcessManager::initProcess(ONEBOT_PROCESS_WORKER, $worker->id);
        Adaptive::initWithDriver(WorkermanDriver::getInstance());
        ob_event_dispatcher()->dispatchWithHandler(new WorkerStartEvent());
    }

    /**
     * Workerman 的顶层 workerStop 事件回调
     */
    public function onWorkerStop()
    {
        ob_event_dispatcher()->dispatchWithHandler(new WorkerStopEvent());
    }

    /**
     * Workerman 的顶层 onWebSocketConnect 事件回调
     *
     * @param TcpConnection $connection 连接本身
     * @param mixed         $data       数据
     */
    public function onWebSocketOpen(array $config, TcpConnection $connection, $data)
    {
        // WebSocket 隐藏特性： _SERVER 全局变量会在 onWebSocketConnect 中被替换为当前连接的 Header 相关信息
        try {
            global $_SERVER;
            $headers = Utils::convertHeaderFromGlobal($_SERVER);
            $server_request = HttpFactory::createServerRequest(
                $_SERVER['REQUEST_METHOD'],
                'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                $headers
            );
            $server_request = $server_request->withQueryParams($_GET);
            $event = new WebSocketOpenEvent($server_request, $connection->id);

            $event->setSocketConfig($config);
            ob_event_dispatcher()->dispatch($event);
            // 判断 response 是不是 101 状态，如果是 101 状态，那么就只取 Header 补充，其他内容丢弃
            if (is_object($event->getResponse()) && $event->getResponse()->getStatusCode() !== 101) {
                $connection->close(method_exists($event->getResponse(), '__toString') ? ((string) $event->getResponse()) : '');
            } elseif (is_object($event->getResponse())) {
                /* @phpstan-ignore-next-line */
                $connection->headers = Utils::getRawHeadersFromResponse($event->getResponse());
            }
            if (($connection->worker instanceof Worker) && ($socket = WorkermanDriver::getInstance()->getWSServerSocketByWorker($connection->worker)) !== null) {
                $socket->connections[$connection->id] = $connection;
            } else {
                // TODO: 编写不可能的异常情况
                ob_logger()->error('WorkermanDriver::getWSServerSocketByWorker() returned null');
            }
        } catch (\Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
            $connection->close();
        }
    }

    /**
     * Workerman 的顶层 onWebSocketClose 事件回调
     */
    public function onWebSocketClose(array $config, TcpConnection $connection)
    {
        if (($connection->worker instanceof Worker) && ($socket = WorkermanDriver::getInstance()->getWSServerSocketByWorker($connection->worker)) !== null) {
            unset($socket->connections[$connection->id]);
        } else {
            // TODO: 编写不可能的异常情况
            ob_logger()->error('WorkermanDriver::getWSServerSocketByWorker() returned null');
        }
        $event = new WebSocketCloseEvent($connection->id);
        $event->setSocketConfig($config);
        ob_event_dispatcher()->dispatch($event);
    }

    /**
     * Workerman 的顶层 onWebSocketMessage 事件回调
     *
     * @param TcpConnection $connection 连接本身
     * @param mixed         $data
     */
    public function onWebSocketMessage(array $config, TcpConnection $connection, $data)
    {
        try {
            ob_logger()->debug('WebSocket message from: ' . $connection->id);
            $frame = FrameFactory::createTextFrame($data);

            $event = new WebSocketMessageEvent($connection->id, $frame, function (int $fd, $data) use ($connection) {
                if ($data instanceof FrameInterface) {
                    $data_w = $data->getData();
                    $res = $connection->send($data_w);
                } else {
                    $res = $connection->send($data);
                }
                return !($res === false);
            });
            $event->setSocketConfig($config);
            ob_event_dispatcher()->dispatch($event);
        } catch (\Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
        }
    }

    public function onHttpRequest(array $config, TcpConnection $connection, Request $request)
    {
        $port = $connection->getLocalPort();
        ob_logger()->debug('Http request from ' . $port . ': ' . $request->uri());
        $event = new HttpRequestEvent(HttpFactory::createServerRequest(
            $request->method(),
            $request->uri(),
            $request->header(),
            $request->rawBody()
        ));
        $event->setSocketConfig($config);
        $send_callable = function (ResponseInterface $psr_response) use ($connection) {
            $response = new WorkermanResponse();
            $response->withStatus($psr_response->getStatusCode());
            $response->withHeaders($psr_response->getHeaders());
            $response->withBody($psr_response->getBody()->getContents());
            $connection->send($response);
        };
        $event->withAsyncResponseCallable($send_callable);
        $response = new WorkermanResponse();
        try {
            ob_event_dispatcher()->dispatch($event);
            if (($psr_response = $event->getResponse()) !== null) {
                $response->withStatus($psr_response->getStatusCode());
                $response->withHeaders($psr_response->getHeaders());
                $response->withBody($psr_response->getBody()->getContents());
                $connection->send($response);
            }
        } catch (\Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
            $response->withStatus(500);
            $response->withBody('Internal Server Error');
            $connection->send($response);
        }
    }
}
