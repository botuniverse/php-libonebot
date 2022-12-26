<?php

declare(strict_types=1);

namespace OneBot\Exception;

interface ExceptionHandlerInterface
{
    public function handle(\Throwable $e): void;
}
