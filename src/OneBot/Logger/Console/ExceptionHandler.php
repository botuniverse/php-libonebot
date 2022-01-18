<?php

declare(strict_types=1);

namespace OneBot\Logger\Console;

use NunoMaduro\Collision\Handler;
use Whoops\Run;

class ExceptionHandler
{
    protected $whoops;

    public function __construct()
    {
        $this->whoops = new Run();
        $this->whoops->pushHandler(new Handler());
    }

    public function enablePrettyPrint(): void
    {
        $this->whoops->register();
    }

    public function disablePrettyPrint(): void
    {
        $this->whoops->unregister();
    }
}
