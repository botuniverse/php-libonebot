<?php

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use OneBot\Driver\Event\EventDispatcher;
use OneBot\Driver\Event\Process\WorkerStartEvent;
use OneBot\Driver\Event\Process\WorkerStopEvent;
use OneBot\Driver\ProcessManager;
use OneBot\Util\Singleton;

class TopEventListener
{
    use Singleton;

    /**
     * Workerman 的顶层 workerStart 事件回调
     */
    public function onWorkerStart(Worker $worker)
    {
        ProcessManager::initProcess(ONEBOT_PROCESS_WORKER, $worker->id);
        EventDispatcher::dispatchWithHandler(new WorkerStartEvent());
    }

    /**
     * Workerman 的顶层 workerStop 事件回调
     */
    public function onWorkerStop()
    {
        EventDispatcher::dispatchWithHandler(new WorkerStopEvent());
    }
}
