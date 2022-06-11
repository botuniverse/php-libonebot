<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace OneBot\Driver\Workerman;

use Exception;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class WorkermanUserProcessTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testProcess()
    {
        $process = new UserProcess(function () {
            echo 'a';
        });
        $process->run();
        $this->assertTrue($process->getPid() >= 0);
    }
}
