<?php

declare(strict_types=1);

namespace OneBot\Driver\Process;

class ExecutionResult
{
    public $code;

    public $stdout;

    public $stderr;

    public function __construct(int $code, $stdout = '', $stderr = '')
    {
        $this->code = $code;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }
}
