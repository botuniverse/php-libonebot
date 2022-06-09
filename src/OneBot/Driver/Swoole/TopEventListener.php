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
use OneBot\Driver\ProcessManager;
use OneBot\Http\HttpFactory;
use OneBot\Http\WebSocket\FrameInterface;
use OneBot\Util\Singleton;
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
    public function onRequest(Request $request, Response $response)
    {
        ob_logger()->debug('Http request: ' . $request->server['request_uri']);
        if (empty($content = $request->rawContent())) {
            $content = null;
        }
        $event = new HttpRequestEvent(HttpFactory::getInstance()->createServerRequest(
            $request->server['request_method'],
            $request->server['request_uri'],
            $request->header,
            $content
        ));
        try {
            (new EventDispatcher())->dispatch($event);
            if (($psr_response = $event->getResponse()) !== null) {
                foreach ($psr_response->getHeaders() as $header => $value) {
                    if (is_array($value)) {
                        $response->setHeader($header, implode(';', $value));
                    }
                }
                $response->setStatusCode($psr_response->getStatusCode());
                $response->end($psr_response->getBody());
            } else {
                $response->setStatusCode(204);
                $response->end();
            }
        } catch (Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
            $response->status(500);
            $response->end('Internal Server Error');
        }
    }

    /**
     * Swoole 的顶层 close 事件回调
     *
     * @param int|string $fd
     */
    public function onClose(?Server $server, $fd)
    {
        ob_logger()->debug('WebSocket closed from: ' . $fd);
        EventDispatcher::dispatchWithHandler(new WebSocketCloseEvent($fd));
    }

    /**
     * Swoole 的顶层 open 事件回调（WebSocket 连接建立事件）
     */
    public function onOpen(SwooleWebSocketServer $server, Request $request)
    {
        ob_logger()->debug('WebSocket connection open: ' . $request->fd);
        if (empty($content = $request->rawContent())) {
            $content = null;
        }
        EventDispatcher::dispatchWithHandler(new WebSocketOpenEvent(HttpFactory::getInstance()->createServerRequest(
            $request->server['request_method'],
            $request->server['request_uri'],
            $request->header,
            $content
        ), $request->fd));
    }

    /**
     * Swoole 的顶层 message 事件回调（WebSocket 消息事件）
     */
    public function onMessage(?SwooleWebSocketServer $server, Frame $frame)
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
        EventDispatcher::dispatchWithHandler($event);
    }
}
