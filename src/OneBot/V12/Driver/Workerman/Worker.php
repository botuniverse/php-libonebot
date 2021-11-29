<?php

declare(strict_types=1);

namespace OneBot\V12\Driver\Workerman;

class Worker extends \Workerman\Worker
{
    public static $internal_running = false;

    protected static function parseCommand()
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
        static::log("Workerman[{$start_file}] {$command} {$mode_str}");

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
}
