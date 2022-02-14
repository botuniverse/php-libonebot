<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use Exception;
use Workerman\Connection\ConnectionInterface;
use Workerman\Events\EventInterface;
use Workerman\Lib\Timer;
use function chmod;
use function count;
use function debug_backtrace;
use function is_file;
use function pcntl_signal;
use function pcntl_signal_dispatch;
use function pcntl_wait;
use function posix_getpid;
use function posix_kill;
use function set_error_handler;
use function str_replace;
use function time;
use function touch;
use function unlink;
use const OS_TYPE_LINUX;
use const SIG_IGN;
use const SIGHUP;
use const SIGINT;
use const SIGKILL;
use const SIGTERM;
use const SIGUSR1;
use const WUNTRACED;

class Worker extends \Workerman\Worker
{
    public static $internal_running = false;

    public static $user_process_pid = -1;

    /**
     * @var UserProcess
     */
    public static $user_process;

    /**
     * @throws Exception
     */
    public static function runAll(): void
    {
        static::checkSapiEnv();     // 判断操作系统类型，如果是 Windows 则会切换为 Windows 模式
        static::init();             // 初始化 Workerman 相关配置，包括「set_error_handler，」
        static::parseCommand();     // 解析命令行，但好像 libob 不太需要
        static::daemonize();        // 创建守护进程，但 libob 好像也不太需要
        static::initWorkers();      // 初始化 Worker 进程
        static::installSignal();    // 安装信号处理函数
        //static::saveMasterPid();    // 保存 Master PID
        //static::displayUI();      // 显示开头的启动信息 UI，但 Swoole 没有，所以这里注释掉，以统一
        static::forkWorkers();      // 创建 Worker 进程
        static::resetStd();         // 重置标准输入输出
        static::monitorWorkers();   // 监控 Worker 进程，这里是一个 while(1) 来等待的
    }

    /**
     * 防止 Workerman 输出了自己的东西
     * @param string $msg
     */
    public static function log($msg)
    {
        ob_logger()->debug($msg);
    }

    /**
     * Stop all.
     *
     * @param int    $code
     * @param string $log
     */
    public static function stopAll($code = 0, $log = '')
    {
        if ($log) {
            static::log($log);
        }

        static::$_status = static::STATUS_SHUTDOWN;
        // For master process.
        if (static::$_masterPid === posix_getpid()) {
            $worker_pid_array = static::getAllWorkerPids();
            // Send stop signal to all child processes.
            if (static::$_gracefulStop) {
                $sig = SIGHUP;
            } else {
                $sig = SIGINT;
            }
            foreach ($worker_pid_array as $worker_pid) {
                posix_kill($worker_pid, $sig);
                if (!static::$_gracefulStop) {
                    Timer::add(static::KILL_WORKER_TIMER_TIME, '\posix_kill', [$worker_pid, SIGKILL], false);
                }
            }
            Timer::add(1, '\\Workerman\\Worker::checkIfChildRunning');
            // Remove statistics file.
            if (is_file(static::$_statisticsFile)) {
                @unlink(static::$_statisticsFile);
            }
        } // For child processes.
        else {
            // Execute exit.
            foreach (static::$_workers as $worker) {
                if (!$worker->stopping) {
                    $worker->stop();
                    $worker->stopping = true;
                }
            }
            if (!static::$_gracefulStop || ConnectionInterface::$statistics['connection_count'] <= 0) {
                static::$_workers = [];
                if (static::$globalEvent) {
                    static::$globalEvent->destroy();
                }

                try {
                    exit($code);
                } catch (Exception $e) {
                }
            }
        }
    }

    /**
     * Init.（重写）
     * 因为需要重写覆盖掉 Workerman 下面的 safeEcho 统一输出格式
     */
    protected static function init()
    {
        set_error_handler(function ($code, $msg, $file, $line) {
            // 这里为重写的部分
            ob_logger()->critical("{$msg} in file {$file} on line {$line}\n");
        });

        // Start file.
        $backtrace = debug_backtrace();
        static::$_startFile = $backtrace[count($backtrace) - 1]['file'];

        $unique_prefix = str_replace('/', '_', static::$_startFile);

        // Pid file.
        // [jerry]: 不需要Workerman自作主张写文件了！！
        /*if (empty(static::$pidFile)) {
            static::$pidFile = __DIR__ . "/../{$unique_prefix}.pid";
        }*/

        // Log file.
        // [jerry]: 不需要Workerman自作主张写文件了！！
        /*
        if (empty(static::$logFile)) {
            static::$logFile = __DIR__ . '/../workerman.log';
        }
        $log_file = (string) static::$logFile;
        if (!is_file($log_file)) {
            touch($log_file);
            chmod($log_file, 0622);
        }*/

        // State.
        static::$_status = static::STATUS_STARTING;

        // For statistics.
        static::$_globalStatistics['start_timestamp'] = time();

        // Process title.
        static::setProcessTitle(static::$processTitle . ': master process  start_file=' . static::$_startFile);

        // Init data for worker id.
        static::initId();

        // Timer init.
        Timer::init();
    }

    protected static function parseCommand(): void
    {
        if (static::$_OS !== OS_TYPE_LINUX) {
            return;
        }

        global $argv;
        // Check argv;
        $start_file = $argv[0];
        $usage = "Usage: php <file> <command> [mode]\nCommands: \nstart\t\tStart worker in DEBUG mode.\n\t\tUse mode -d to start in DAEMON mode.\nstop\t\tStop worker.\n\t\tUse mode -g to stop gracefully.\nrestart\t\tRestart workers.\n\t\tUse mode -d to start in DAEMON mode.\n\t\tUse mode -g to stop gracefully.\nreload\t\tReload codes.\n\t\tUse mode -g to reload gracefully.\nstatus\t\tGet worker status.\n\t\tUse mode -d to show live status.\nconnections\tGet worker connections.\n";
        $available_commands = [
            'start',
            'stop',
            'restart',
            'reload',
            'status',
            'connections',
        ];
        $available_mode = [
            '-d',
            '-g',
        ];
        $command = $mode = '';
        foreach ($argv as $value) {
            if (in_array($value, $available_commands)) {
                $command = $value;
            } elseif (in_array($value, $available_mode)) {
                $mode = $value;
            }
        }
        if (self::$internal_running) {
            $command = 'start';
        }
        if (!$command) {
            exit($usage);
        }
        // Start command.
        $mode_str = '';
        if ($command === 'start') {
            if ($mode === '-d' || static::$daemonize) {
                $mode_str = 'in DAEMON mode';
            } else {
                $mode_str = 'in DEBUG mode';
            }
        }
        start:
        //static::log("Workerman[{$start_file}] {$command} {$mode_str}");

        // Get master process PID.
        $master_pid = is_file(static::$pidFile) ? (int) file_get_contents(static::$pidFile) : 0;
        // Master is still alive?
        if (static::checkMasterIsAlive($master_pid)) {
            if ($command === 'start') {
                static::log("Workerman[{$start_file}] already running");
                exit;
            }
        } elseif ($command !== 'start' && $command !== 'restart') {
            static::log("Workerman[{$start_file}] not run");
            exit;
        }

        $statistics_file = __DIR__ . "/../workerman-{$master_pid}.status";

        // execute command.
        switch ($command) {
            case 'start':
                if ($mode === '-d') {
                    static::$daemonize = true;
                }
                break;
            case 'status':
                while (1) {
                    if (is_file($statistics_file)) {
                        @unlink($statistics_file);
                    }
                    // Master process will send SIGUSR2 signal to all child processes.
                    posix_kill($master_pid, SIGUSR2);
                    // Sleep 1 second.
                    sleep(1);
                    // Clear terminal.
                    if ($mode === '-d') {
                        static::safeEcho("\33[H\33[2J\33(B\33[m", true);
                    }
                    // Echo status data.
                    static::safeEcho(static::formatStatusData($statistics_file));
                    if ($mode !== '-d') {
                        exit(0);
                    }
                    static::safeEcho("\nPress Ctrl+C to quit.\n\n");
                }
            // no break
            case 'connections':
                if (is_file($statistics_file) && is_writable($statistics_file)) {
                    unlink($statistics_file);
                }
                // Master process will send SIGIO signal to all child processes.
                posix_kill($master_pid, SIGIO);
                // Waiting a moment.
                usleep(500000);
                // Display statistics data from a disk file.
                if (is_readable($statistics_file)) {
                    readfile($statistics_file);
                }
                exit(0);
            case 'restart':
            case 'stop':
                if ($mode === '-g') {
                    static::$_gracefulStop = true;
                    $sig = SIGHUP;
                    static::log("Workerman[{$start_file}] is gracefully stopping ...");
                } else {
                    static::$_gracefulStop = false;
                    $sig = SIGINT;
                    static::log("Workerman[{$start_file}] is stopping ...");
                }
                // Send stop signal to master process.
                $master_pid && posix_kill($master_pid, $sig);
                // Timeout.
                $timeout = 5;
                $start_time = time();
                // Check master process is still alive?
                while (1) {
                    $master_is_alive = $master_pid && posix_kill($master_pid, 0);
                    if ($master_is_alive) {
                        // Timeout?
                        if (!static::$_gracefulStop && time() - $start_time >= $timeout) {
                            static::log("Workerman[{$start_file}] stop fail");
                            exit;
                        }
                        // Waiting a moment.
                        usleep(10000);
                        continue;
                    }
                    // Stop success.
                    static::log("Workerman[{$start_file}] stop success");
                    if ($command === 'stop') {
                        exit(0);
                    }
                    if ($mode === '-d') {
                        static::$daemonize = true;
                    }
                    break;
                }
                break;
            case 'reload':
                if ($mode === '-g') {
                    $sig = SIGQUIT;
                } else {
                    $sig = SIGUSR1;
                }
                posix_kill($master_pid, $sig);
                exit;
            default:
                static::safeEcho('Unknown command: ' . $command . "\n");
                exit($usage);
        }
    }

    /**
     * Install signal handler.
     */
    protected static function installSignal()
    {
        if (static::$_OS !== OS_TYPE_LINUX) {
            return;
        }
        $signalHandler = '\Workerman\Worker::signalHandler';
        // stop
        //\pcntl_signal(\SIGINT, $signalHandler, false);
        // stop
        pcntl_signal(SIGTERM, $signalHandler, false);
        // graceful stop
        //\pcntl_signal(\SIGHUP, $signalHandler, false);
        // reload
        pcntl_signal(SIGUSR1, $signalHandler, false);
        // graceful reload
        //\pcntl_signal(\SIGQUIT, $signalHandler, false);
        // status
        //\pcntl_signal(\SIGUSR2, $signalHandler, false);
        // connection status
        //\pcntl_signal(\SIGIO, $signalHandler, false);
        // ignore
        //\pcntl_signal(\SIGPIPE, \SIG_IGN, false);
    }

    /**
     * Reinstall signal handler.
     */
    protected static function reinstallSignal()
    {
        if (static::$_OS !== OS_TYPE_LINUX) {
            return;
        }
        $signalHandler = '\Workerman\Worker::signalHandler';
        // uninstall stop signal handler
        //\pcntl_signal(\SIGINT, \SIG_IGN, false);
        // uninstall stop signal handler
        pcntl_signal(SIGTERM, SIG_IGN, false);
        // uninstall graceful stop signal handler
        //\pcntl_signal(\SIGHUP, \SIG_IGN, false);
        // uninstall reload signal handler
        pcntl_signal(SIGUSR1, SIG_IGN, false);
        // uninstall graceful reload signal handler
        //\pcntl_signal(\SIGQUIT, \SIG_IGN, false);
        // uninstall status signal handler
        //\pcntl_signal(\SIGUSR2, \SIG_IGN, false);
        // uninstall connections status signal handler
        //\pcntl_signal(\SIGIO, \SIG_IGN, false);
        // reinstall stop signal handler
        //static::$globalEvent->add(\SIGINT, EventInterface::EV_SIGNAL, $signalHandler);
        // reinstall graceful stop signal handler
        //static::$globalEvent->add(\SIGHUP, EventInterface::EV_SIGNAL, $signalHandler);
        // reinstall reload signal handler
        static::$globalEvent->add(SIGUSR1, EventInterface::EV_SIGNAL, $signalHandler);
        // reinstall graceful reload signal handler
        //static::$globalEvent->add(\SIGQUIT, EventInterface::EV_SIGNAL, $signalHandler);
        // reinstall status signal handler
        //static::$globalEvent->add(\SIGUSR2, EventInterface::EV_SIGNAL, $signalHandler);
        // reinstall connection status signal handler
        //static::$globalEvent->add(\SIGIO, EventInterface::EV_SIGNAL, $signalHandler);
    }

    /**
     * Set process name.
     *
     * @param string $title
     */
    protected static function setProcessTitle($title)
    {
        // 为了保持 Swoole 和 Workerman 行为一致，在这里我们不需要默认重命名进程名字
        /*
        \set_error_handler(function(){});
        // >=php 5.5
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title($title);
        } // Need proctitle when php<=5.5 .
        elseif (\extension_loaded('proctitle') && \function_exists('setproctitle')) {
            \setproctitle($title);
        }
        \restore_error_handler();
        */
    }

    /**
     * Monitor all child processes.
     */
    protected static function monitorWorkersForLinux()
    {
        static::$_status = static::STATUS_RUNNING;
        while (1) {
            // Calls signal handlers for pending signals.
            pcntl_signal_dispatch();
            // Suspends execution of the current process until a child has exited, or until a signal is delivered
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED);
            // Calls signal handlers for pending signals again.
            pcntl_signal_dispatch();
            // If a child has already exited.
            if ($pid > 0) {
                // 这里插入用户持久进程的退出监听事件
                if ($pid === static::$user_process_pid) {
                    ob_logger()->debug('用户进程退出！状态码：' . $status);
                    usleep(500000);
                }
                // Find out which worker process exited.
                foreach (static::$_pidMap as $worker_id => $worker_pid_array) {
                    if (isset($worker_pid_array[$pid])) {
                        $worker = static::$_workers[$worker_id];
                        // Exit status.
                        if ($status !== 0) {
                            static::log('worker[' . $worker->name . ":{$pid}] exit with status {$status}");
                        }

                        // For Statistics.
                        if (!isset(static::$_globalStatistics['worker_exit_info'][$worker_id][$status])) {
                            static::$_globalStatistics['worker_exit_info'][$worker_id][$status] = 0;
                        }
                        ++static::$_globalStatistics['worker_exit_info'][$worker_id][$status];

                        // Clear process data.
                        unset(static::$_pidMap[$worker_id][$pid]);

                        // Mark id is available.
                        $id = static::getId($worker_id, $pid);
                        static::$_idMap[$worker_id][$id] = 0;

                        break;
                    }
                }
                // Is still running state then fork a new worker process.
                if (static::$_status !== static::STATUS_SHUTDOWN) {
                    static::forkWorkers();
                    if ($pid === static::$user_process_pid) {
                        static::$user_process->rerun();
                        static::$user_process_pid = static::$user_process->getPid();
                    }
                    // If reloading continue.
                    if (isset(static::$_pidsToRestart[$pid])) {
                        unset(static::$_pidsToRestart[$pid]);
                        static::reload();
                    }
                }
            }

            // If shutdown state and all child processes exited then master process exit.
            if (static::$_status === static::STATUS_SHUTDOWN && !static::getAllWorkerPids()) {
                static::exitAndClearAll();
            }
        }
    }
}
