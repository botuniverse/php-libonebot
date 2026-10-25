<?php

declare(strict_types=1);

namespace OneBot\Driver\Swoole;

use Choir\Http\HttpFactory;
use Choir\Http\UploadedFile;
use Choir\WebSocket\FrameInterface;
use OneBot\Driver\Coroutine\Adaptive;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Driver\Interfaces\StreamResponseInterface;
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
     * Swoole 的顶层 workerError 事件回调（在 Manager 进程内触发）
     *
     * 这是 Swoole 唯一会告诉你「worker 为什么死」的地方：exit_code + signal。
     * 常见对应关系：exit_code=255 → PHP Fatal Error / 未捕获异常；signal=11 → 段错误；
     * signal=9 → 被 kill -9 或 OOM 杀掉；signal=13 → SIGPIPE（往已关闭的管道/tty 写数据）；
     * signal=15/2 → 正常停止请求（reload/重启）。
     *
     * 两个设计取舍：
     * 1. 不经过 ob_logger() 记日志：worker 崩掉的原因很可能就是控制台输出坏了
     *    （IDE 终端被关掉后 fd 被 revoked），再用 logger 写一次等于把 Manager 也一起带走。
     *    所以这里直接落盘，只在落盘失败时才退回 error_log。
     * 2. 顺带做崩溃熔断：Swoole 的 Manager 对 worker 异常退出没有任何退避，会以最快速度
     *    无限重启。一旦 worker 处于「启动即死」状态，就会变成每秒几十次 fork 的风暴，
     *    把 CPU 打满且服务完全不可用。熔断把这种「无声灾难」变成一个明确的停机 + 一条日志。
     */
    public function onWorkerError(Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {
        $driver = SwooleDriver::getInstance();
        $window = max(1, (int) $driver->getParam('worker_crash_window', 10));
        $limit = (int) $driver->getParam('worker_crash_limit', 10);
        $action = (string) $driver->getParam('worker_crash_action', 'shutdown');

        // 只统计时间窗内的崩溃次数，窗口外的旧记录丢弃
        $now = microtime(true);
        self::$worker_crash_times[] = $now;
        self::$worker_crash_times = array_values(array_filter(
            self::$worker_crash_times,
            static fn (float $ts): bool => $now - $ts <= $window
        ));
        $recent = count(self::$worker_crash_times);

        $this->writeWorkerErrorLog(sprintf(
            'Worker 异常退出: worker_id=%d, pid=%d, exit_code=%d, signal=%d(%s), 近 %d 秒内第 %d 次%s',
            $worker_id,
            $worker_pid,
            $exit_code,
            $signal,
            self::getSignalName($signal),
            $window,
            $recent,
            self::getSignalHint($exit_code, $signal)
        ));

        if ($limit > 0 && $recent >= $limit) {
            $abort = $action !== 'none';
            $this->writeWorkerErrorLog(sprintf(
                '检测到 Worker 崩溃风暴：%d 秒内 %d 次（阈值 %d）。%s',
                $window,
                $recent,
                $limit,
                $abort
                    ? '已主动中止服务器，避免 Manager 无限重启把 CPU 打满。请根据上面的 exit_code/signal 排查根因。'
                    : 'worker_crash_action=none，仅记录不停机，请尽快排查根因。'
            ));
            if ($abort) {
                $server->shutdown();
            }
        }
    }

    /** @var float[] 最近发生 worker 崩溃的时间戳（只在 Manager 进程内有效，进程重启后自动清零） */
    private static array $worker_crash_times = [];

    /** @var array<int, string> 常见信号名 */
    private static array $signal_names = [
        1 => 'SIGHUP', 2 => 'SIGINT', 3 => 'SIGQUIT', 4 => 'SIGILL', 6 => 'SIGABRT', 8 => 'SIGFPE',
        9 => 'SIGKILL', 10 => 'SIGBUS', 11 => 'SIGSEGV', 13 => 'SIGPIPE', 14 => 'SIGALRM',
        15 => 'SIGTERM', 24 => 'SIGXCPU', 25 => 'SIGXFSZ',
    ];

    private static function getSignalName(int $signal): string
    {
        return self::$signal_names[$signal] ?? 'SIG' . $signal;
    }

    private static function getSignalHint(int $exit_code, int $signal): string
    {
        return match ($signal) {
            11, 10, 4 => '（段错误/总线错误：多半是 PHP 扩展或框架层的非法内存访问，注意日志/IO 递归写入坏掉的控制台也可能触发）',
            6 => '（abort：PHP 内部致命错误）',
            9 => '（被 kill -9 或系统因内存不足杀掉）',
            13 => '（SIGPIPE：往已关闭的管道/tty 写数据，常见于启动它的终端被关掉）',
            15, 2 => '（收到正常停止信号，若频繁出现请检查是否有外部进程在反复 kill/reload）',
            0 => $exit_code === 255 ? '（未捕获异常或 PHP Fatal Error，需要看 worker 的标准错误输出）' : '',
            default => '',
        };
    }

    /**
     * 把 worker 崩溃信息写到 zm_data/logs/worker_error.log
     *
     * 刻意不使用 ob_logger()：控制台输出可能正是坏掉的那个 fd。
     */
    private function writeWorkerErrorLog(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] [WORKER_ERROR] ' . $message . PHP_EOL;
        try {
            $base = defined('WORKING_DIR') ? WORKING_DIR : (string) getcwd();
            $dir = $base . '/zm_data/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $file = $dir . '/worker_error.log';
            // 防止反复崩溃把磁盘写满：单文件超过 10MB 滚动一次
            if (is_file($file) && filesize($file) > 10 * 1024 * 1024) {
                @rename($file, $file . '.1');
            }
            if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
        } catch (\Throwable $e) {
            // 落盘失败也不能让 Manager 崩掉，继续走下面的 error_log 兜底
        }
        @error_log(rtrim($line));
    }

    /**
     * Swoole 的顶层 httpRequest 事件回调
     */
    public function onRequest(array $config, Request $request, Response $response)
    {
        try {
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
            // 上传的文件
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
            // post 包体
            if (!empty($request->post)) {
                $req = $req->withParsedBody($request->post);
            }
            $event = new HttpRequestEvent($req);
            $event->setSocketConfig($config);
            ob_event_dispatcher()->dispatch($event);
            if (($psr_response = $event->getResponse()) !== null) {
                // 流式响应（SSE / 打字机）交给 writeTo() 边算边发，不走这里的「攒齐再 end」
                if ($psr_response instanceof StreamResponseInterface) {
                    $this->streamResponse($psr_response, $response);
                    return;
                }
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
            if (isset($event) && is_callable($event->getErrorHandler())) {
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
     * 输出流式响应（SSE / 打字机 / 上游透传）
     *
     * 与普通响应的区别：不把 body 攒齐再 end()，而是把控制权交给响应对象的 writeTo()，
     * 由它边生产边通过 sink 写出。这里只负责三件事：
     * 1. 先把状态码和响应头发出去（客户端才能尽早进入 streaming 状态）；
     * 2. 兜住 writeTo() 里的异常——此时头已发出，改不了状态码，只能尽力补一条错误事件；
     * 3. 保证连接最终被 end() 收尾，不会把 worker 挂死在一个永远不结束的请求上。
     */
    private function streamResponse(StreamResponseInterface $psr_response, Response $response): void
    {
        foreach ($psr_response->getHeaders() as $header => $value) {
            // 流式响应必须不带 Content-Length，否则 Swoole 不会使用 chunked，客户端会一直等
            if (strcasecmp($header, 'Content-Length') === 0) {
                continue;
            }
            $response->setHeader($header, implode(';', $value));
        }
        $response->setStatusCode($psr_response->getStatusCode());

        $sink = new SwooleStreamSink($response);
        try {
            $psr_response->writeTo($sink);
        } catch (\Throwable $e) {
            try {
                ExceptionHandler::getInstance()->handle($e);
            } catch (\Throwable $ignore) {
                // 连异常处理器都炸了就别再往外抛，否则整个 worker 会挂掉
            }
            if (!$sink->isClosed() && stripos($psr_response->getHeaderLine('Content-Type'), 'text/event-stream') !== false) {
                $sink->write('event: error' . "\n" . 'data: ' . json_encode(['message' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . "\n\n");
            }
        } finally {
            $sink->end();
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
        try {
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
        } catch (\Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
            return false;
        }
    }

    /**
     * Swoole 的顶层 message 事件回调（WebSocket 消息事件）
     */
    public function onMessage(array $config, ?SwooleWebSocketServer $server, Frame $frame)
    {
        try {
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
        } catch (\Throwable $e) {
            ExceptionHandler::getInstance()->handle($e);
        }
    }
}
