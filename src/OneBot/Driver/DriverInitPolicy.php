<?php

declare(strict_types=1);

namespace OneBot\Driver;

interface DriverInitPolicy
{
    /**
     * 多进程时在 Master 进程内执行 driver.init
     */
    public const MULTI_PROCESS_INIT_IN_MASTER = 1;

    /**
     * 多进程时在 Manager 进程内执行 driver.init
     * 如果没有 Manager 进程，则执行在 Master 进程内
     */
    public const MULTI_PROCESS_INIT_IN_MANAGER = 2;

    /**
     * （多进程时的默认策略）
     * 多进程时在第一个 Worker 进程内执行 driver.init
     * 如果 Worker 重启，则会再次执行 driver.init
     */
    public const MULTI_PROCESS_INIT_IN_FIRST_WORKER = 3;

    /**
     * 多进程时另启一个进程执行 driver.init
     */
    public const MULTI_PROCESS_INIT_IN_USER_PROCESS = 4;
}
