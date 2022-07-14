<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use Exception;
use OneBot\Driver\Interfaces\ProcessInterface;
use OneBot\Driver\Process\ProcessManager;
use Throwable;

class UserProcess implements ProcessInterface
{
    /** @var callable */
    private $callable;

    /** @var int */
    private $pid;

    /** @var bool */
    private $is_running = false;

    /** @var int */
    private $status;

    /**
     * @param  mixed     $callable
     * @throws Exception
     * @internal
     */
    public function __construct($callable)
    {
        if (!ProcessManager::isSupportedMultiProcess()) {
            throw new Exception('Multi-process is not supported on this environment');
        }
        if (!is_callable($callable)) {
            throw new Exception('Process expects a callable callback');
        }
        ProcessManager::initProcess(ONEBOT_PROCESS_USER, -1);
        $this->callable = $callable;
    }

    /**
     * @throws Exception
     */
    public function run()
    {
        if ($this->isRunning()) {
            throw new Exception('The process is already running');
        }
        $this->rerun();
        Worker::$user_process_pid = $this->pid;
    }

    /**
     * @internal
     * @throws Exception
     */
    public function rerun()
    {
        $this->pid = pcntl_fork();
        if ($this->pid == -1) {
            throw new Exception('Could not fork');
        }
        if ($this->pid !== 0) {
            $this->is_running = true;
        } else {
            $this->pid = posix_getpid();
            try {
                $exit_code = call_user_func($this->callable);
            } catch (Throwable $e) {
                $exit_code = 255;
            }
            exit((int) $exit_code);
        }
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    /**
     * @throws Exception
     */
    public function wait()
    {
        if ($this->isRunning()) {
            $this->updateStatus(true);
        }
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @throws Exception
     */
    public function isRunning(): bool
    {
        if (!$this->is_running) {
            return false;
        }
        $this->updateStatus();
        return $this->is_running;
    }

    /**
     * @throws Exception
     */
    private function updateStatus(bool $blocking = false)
    {
        if (!$this->is_running) {
            return;
        }
        $options = $blocking ? 0 : WNOHANG | WUNTRACED;
        $result = pcntl_waitpid($this->getPid(), $status, $options);
        if ($result === -1) {
            throw new Exception('Error waits on or returns the status of the process');
        }
        if ($result) {
            $this->is_running = false;
            $this->status = $status;
        } else {
            $this->is_running = true;
        }
    }
}
