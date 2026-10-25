<?php

declare(strict_types=1);

namespace Tests\OneBot\Driver;

use OneBot\Driver\WorkerCrashGuard;
use PHPUnit\Framework\TestCase;

class WorkerCrashGuardTest extends TestCase
{
    private string $log_dir;

    protected function setUp(): void
    {
        $this->log_dir = sys_get_temp_dir() . '/ob_crash_guard_test_' . getmypid();
        WorkerCrashGuard::setLogDir($this->log_dir);
        WorkerCrashGuard::reset();
    }

    protected function tearDown(): void
    {
        WorkerCrashGuard::setLogDir(null);
        WorkerCrashGuard::reset();
        @unlink($this->log_dir . '/worker_error.log');
        @rmdir($this->log_dir);
    }

    public function testReportsAndTripsAtThreshold(): void
    {
        $tripped = false;
        for ($i = 1; $i <= 10; ++$i) {
            $tripped = WorkerCrashGuard::report('test', 1000 + $i, 0, 11, ['window' => 30, 'limit' => 10]);
            self::assertSame($i, WorkerCrashGuard::recentCount());
            if ($i < 10) {
                self::assertFalse($tripped, "第 {$i} 次不应触发熔断");
            }
        }
        self::assertTrue($tripped, '第 10 次应触发熔断');
    }

    public function testLimitZeroDisablesBreaker(): void
    {
        for ($i = 0; $i < 20; ++$i) {
            self::assertFalse(WorkerCrashGuard::report('test', 2000 + $i, 255, 0, ['window' => 30, 'limit' => 0]));
        }
    }

    public function testWritesLogFileWithSignalDetail(): void
    {
        WorkerCrashGuard::report('swoole', 4242, 0, 11, ['window' => 30, 'limit' => 10]);

        $file = $this->log_dir . '/worker_error.log';
        self::assertFileExists($file);
        $content = (string) file_get_contents($file);
        self::assertStringContainsString('worker 异常退出', $content);
        self::assertStringContainsString('pid=4242', $content);
        self::assertStringContainsString('SIGSEGV', $content);
        self::assertStringContainsString('swoole', $content);
    }

    public function testSignalNamesAndHints(): void
    {
        self::assertSame('SIGSEGV', WorkerCrashGuard::signalName(11));
        self::assertSame('SIGKILL', WorkerCrashGuard::signalName(9));
        self::assertSame('SIG42', WorkerCrashGuard::signalName(42));
        self::assertStringContainsString('未捕获异常', WorkerCrashGuard::signalHint(255, 0));
        self::assertStringContainsString('SIGPIPE', WorkerCrashGuard::signalHint(0, 13));
    }
}
