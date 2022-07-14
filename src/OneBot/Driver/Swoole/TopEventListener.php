<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole;

use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Event\Process\ManagerStartEvent;
use OneBot\Driver\Event\Process\ManagerStopEvent;
use OneBot\Driver\Event\Process\WorkerStartEvent;
use OneBot\Driver\Event\Process\WorkerStopEvent;
use OneBot\Driver\Event\WebSocket\WebSocketCloseEvent;
use OneBot\Driver\Event\WebSocket\WebSocketMessageEvent;
use OneBot\Driver\Event\WebSocket\WebSocketOpenEvent;
use OneBot\Driver\ExceptionHandler;
use OneBot\Driver\Process\ProcessManager;
use OneBot\Http\HttpFactory;
use OneBot\Http\WebSocket\FrameInterface;
use OneBot\Util\Singleton;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as SwooleWebSocketServer;
use Throwable;

class TopEventListener
{
    use Singleton;

    /**
     * Swoole 的顶层 workerStart 事件回调
     */
    public function onWorkerStart(Server $server)
    {
        ProcessManager::initProcess(ONEBOT_PROCESS_WORKER, $server->worker_id);
        EventDispatcher::dispatchWithHandler(new WorkerStartEvent());
    }

    /**
     * Swoole 的顶层 managerStart 事件回调
     */
    public function onManagerStart()
    {
        ProcessManager::initProcess(ONEBOT_PROCESS_MANAGER, -1);
        EventDispatcher::dispatchWithHandler(new ManagerStartEvent());
    }

    /**
     * Swoole 的顶层 managerStop 事件回调
     */
    public function onManagerStop()
    {
        EventDispatcher::dispatchWithHandler(new ManagerStopEvent());
    }

    /**
     * Swoole 的顶层 workerStop 事件回调
     */
    public function onWorkerStop()
    {
        EventDispatcher::dispatchWithHandler(new WorkerStopEvent());
    }

    /**
     * Swoole 的顶层 httpRequest 事件回调
     */
    public function onRequest(int $flag, Request $request, Response $response)
    {
        ob_logger()->debug('Http request: ' . $request->server['request_uri']);
        if (empty($content = $request->rawContent()) && $content !== '0') { // empty 遇到纯0的请求会返回true，所以这里加上 !== '0'
            $content = null;
        }
        $req = HttpFactory::getInstance()->createServerRequest(
            $request->server['request_method'],
            $request->server['request_uri'],
            $request->header,
            $content
        );
        $req = $req->withQueryParams($request->get ?? []);
        $event = new HttpRequestEvent($req);
        try {
            $event->setSocketFlag($flag);
            (new EventDispatcher())->dispatch($event);
            if (($psr_response = $event->getResponse()) !== null) {
                foreach ($psr_response->getHeaders() as $header => $value) {
                    if (is_array($value)) {
                        $response->setHeader($header, implode(';', $value));
                    }
                }
                ob_dump($psr_response);
                $response->setStatusCode($psr_response->getStatusCode());
                $response->end($psr_response->getBody());
            }
        } catch (Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
            if (is_callable($event->getErrorHandler())) {
                $err_response = call_user_func($event->getErrorHandler(), $e, $event);
                if ($err_response instanceof ResponseInterface) {
                    foreach ($err_response->getHeaders() as $header => $value) {
                        if (is_array($value)) {
                            $response->setHeader($header, implode(';', $value));
                        }
                    }
                    $response->setStatusCode($err_response->getStatusCode());
                    $response->end($err_response->getBody());
                    return;
                }
            }
            $response->status(500);
            $response->end('Internal Server Error');
        }
    }

    /**
     * Swoole 的顶层 close 事件回调
     *
     * @param int|string $fd
     */
    public function onClose(int $flag, ?Server $server, $fd)
    {
        ob_logger()->debug('WebSocket closed from: ' . $fd);
        $event = new WebSocketCloseEvent($fd);
        $event->setSocketFlag($flag);
        EventDispatcher::dispatchWithHandler($event);
    }

    /**
     * Swoole 的顶层 open 事件回调（WebSocket 连接建立事件）
     */
    public function onOpen(int $flag, SwooleWebSocketServer $server, Request $request)
    {
        ob_logger()->debug('WebSocket connection open: ' . $request->fd);
        if (empty($content = $request->rawContent())) {
            $content = null;
        }
        $event = new WebSocketOpenEvent(HttpFactory::getInstance()->createServerRequest(
            $request->server['request_method'],
            $request->server['request_uri'],
            $request->header,
            $content
        ), $request->fd);
        $event->setSocketFlag($flag);
        EventDispatcher::dispatchWithHandler($event);
    }

    /**
     * Swoole 的顶层 message 事件回调（WebSocket 消息事件）
     */
    public function onMessage(int $flag, ?SwooleWebSocketServer $server, Frame $frame)
    {
        ob_logger()->debug('WebSocket message from: ' . $frame->fd);
        $new_frame = new \OneBot\Http\WebSocket\Frame($frame->data, $frame->opcode, true);
        $event = new WebSocketMessageEvent($frame->fd, $new_frame, function (int $fd, $data) use ($server) {
            if ($data instanceof FrameInterface) {
                return $server->push($fd, $data->getData(), $data->getOpcode());
            }
            return $server->push($fd, $data);
        });
        $event->setOriginFrame($frame);
        $event->setSocketFlag($flag);
        EventDispatcher::dispatchWithHandler($event);
    }
}
