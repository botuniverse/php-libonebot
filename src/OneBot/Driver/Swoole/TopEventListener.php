<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole;

use Choir\Http\HttpFactory;
use Choir\Http\UploadedFile;
use Choir\WebSocket\FrameInterface;
use OneBot\Driver\Coroutine\Adaptive;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Event\Process\ManagerStartEvent;
use OneBot\Driver\Event\Process\ManagerStopEvent;
use OneBot\Driver\Event\Process\WorkerExitEvent;
use OneBot\Driver\Event\Process\WorkerStartEvent;
use OneBot\Driver\Event\Process\WorkerStopEvent;
use OneBot\Driver\Event\WebSocket\WebSocketCloseEvent;
use OneBot\Driver\Event\WebSocket\WebSocketMessageEvent;
use OneBot\Driver\Event\WebSocket\WebSocketOpenEvent;
use OneBot\Driver\Process\ProcessManager;
use OneBot\Exception\ExceptionHandler;
use OneBot\Util\Singleton;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as SwooleWebSocketServer;

class TopEventListener
{
    use Singleton;

    /**
     * Swoole 的顶层 workerStart 事件回调
     */
    public function onWorkerStart(Server $server)
    {
        if ($server->master_pid === $server->worker_pid) {
            ProcessManager::initProcess(ONEBOT_PROCESS_MASTER | ONEBOT_PROCESS_WORKER, $server->worker_id);
        } else {
            ProcessManager::initProcess($server->taskworker ? ONEBOT_PROCESS_WORKER | ONEBOT_PROCESS_TASKWORKER : ONEBOT_PROCESS_WORKER, $server->worker_id);
        }
        Adaptive::initWithDriver(SwooleDriver::getInstance());
        ob_event_dispatcher()->dispatchWithHandler(new WorkerStartEvent());
    }

    /**
     * Swoole 的顶层 managerStart 事件回调
     */
    public function onManagerStart()
    {
        ProcessManager::initProcess(ONEBOT_PROCESS_MANAGER, -1);
        ob_event_dispatcher()->dispatchWithHandler(new ManagerStartEvent());
    }

    /**
     * Swoole 的顶层 managerStop 事件回调
     */
    public function onManagerStop()
    {
        ob_event_dispatcher()->dispatchWithHandler(new ManagerStopEvent());
    }

    /**
     * Swoole 的顶层 workerStop 事件回调
     */
    public function onWorkerStop()
    {
        ob_event_dispatcher()->dispatchWithHandler(new WorkerStopEvent());
    }

    /**
     * Swoole 的顶层 workerExit 事件回调
     */
    public function onWorkerExit()
    {
        ob_event_dispatcher()->dispatchWithHandler(new WorkerExitEvent());
    }

    /**
     * Swoole 的顶层 httpRequest 事件回调
     */
    public function onRequest(array $config, Request $request, Response $response)
    {
        ob_logger()->debug('Http request: ' . $request->server['request_uri']);
        if (empty($content = $request->rawContent()) && $content !== '0') { // empty 遇到纯0的请求会返回true，所以这里加上 !== '0'
            $content = null;
        }
        $req = HttpFactory::createServerRequest(
            $request->server['request_method'],
            $request->server['request_uri'],
            $request->header,
            $content
        );
        $req = $req->withQueryParams($request->get ?? [])
            ->withCookieParams($request->cookie ?? []);
        $uploaded = [];
        if (!empty($request->files)) {
            foreach ($request->files as $key => $value) {
                $upload = new UploadedFile([
                    'key' => $key,
                    ...$value,
                ]);
                $uploaded[] = $upload;
            }
            if ($uploaded !== []) {
                $req = $req->withUploadedFiles($uploaded);
            }
        }
        $event = new HttpRequestEvent($req);
        try {
            $event->setSocketConfig($config);
            ob_event_dispatcher()->dispatch($event);
            if (($psr_response = $event->getResponse()) !== null) {
                foreach ($psr_response->getHeaders() as $header => $value) {
                    if (is_array($value)) {
                        $response->setHeader($header, implode(';', $value));
                    }
                }
                $response->setStatusCode($psr_response->getStatusCode());
                $response->end($psr_response->getBody()->getContents());
            }
        } catch (\Throwable $e) {
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
                    $response->end($err_response->getBody()->getContents());
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
    public function onClose(array $config, ?Server $server, $fd)
    {
        ob_logger()->debug('WebSocket closed from: ' . $fd);
        $event = new WebSocketCloseEvent($fd);
        $event->setSocketConfig($config);
        ob_event_dispatcher()->dispatchWithHandler($event);
    }

    /**
     * Swoole 的顶层 handshake 事件回调（WebSocket 连接建立事件）
     */
    public function onHandshake(array $config, Request $request, Response $response)
    {
        ob_logger()->debug('WebSocket connection handahske: ' . $request->fd);
        if (empty($content = $request->rawContent())) {
            $content = null;
        }
        $event = new WebSocketOpenEvent(HttpFactory::createServerRequest(
            $request->server['request_method'],
            $request->server['request_uri'],
            $request->header,
            $content
        ), $request->fd);
        $event->setSocketConfig($config);
        ob_event_dispatcher()->dispatchWithHandler($event);
        // 检查有没有制定response
        if (is_object($event->getResponse())) {
            $user_resp = $event->getResponse();
            // 不等于 101 bu不握手
            if ($user_resp->getStatusCode() !== 101) {
                foreach ($user_resp->getHeaders() as $header => $value) {
                    if (is_array($value)) {
                        $response->setHeader($header, implode(';', $value));
                    }
                }
                $response->setStatusCode($user_resp->getStatusCode());
                $response->end($user_resp->getBody()->getContents());
                return false;
            }
            // 等于 101 时候，检查把 Header 合进来
            $my_headers = [];
            foreach ($user_resp->getHeaders() as $header => $value) {
                if (is_array($value)) {
                    $my_headers[$header] = implode(';', $value);
                }
            }
        }
        // 手动实现握手
        // websocket握手连接算法验证
        $sec_websocket_key = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (preg_match($patten, $sec_websocket_key) === 0 || strlen(base64_decode($sec_websocket_key)) !== 16) {
            $response->end();
            return false;
        }
        echo $request->header['sec-websocket-key'];
        $key = base64_encode(
            sha1(
                $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
                true
            )
        );
        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
        ];
        if (isset($my_headers)) {
            $headers = array_merge($headers, $my_headers);
        }
        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }

        $response->status(101);
        $response->end();
        return true;
    }

    /**
     * Swoole 的顶层 message 事件回调（WebSocket 消息事件）
     */
    public function onMessage(array $config, ?SwooleWebSocketServer $server, Frame $frame)
    {
        ob_logger()->debug('WebSocket message from: ' . $frame->fd);
        $new_frame = new \Choir\WebSocket\Frame($frame->data, $frame->opcode, true, true);
        $event = new WebSocketMessageEvent($frame->fd, $new_frame, function (int $fd, $data) use ($server) {
            if ($data instanceof FrameInterface) {
                return $server->push($fd, $data->getData(), $data->getOpcode());
            }
            return $server->push($fd, $data);
        });
        $event->setOriginFrame($frame);
        $event->setSocketConfig($config);
        ob_event_dispatcher()->dispatchWithHandler($event);
    }
}
