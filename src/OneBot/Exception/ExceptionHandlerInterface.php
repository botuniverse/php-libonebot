<?php

declare(strict_types=1);

namespace OneBot\Exception;

use Throwable;

interface ExceptionHandlerInterface
{
    public function handle(Throwable $e): void;
}
