<?php

declare(strict_types=1);

namespace Tests\OneBot\Exception;

use OneBot\Exception\ExceptionHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @internal
 */
class ExceptionHandlerTest extends TestCase
{
    public function testCanHandleWithoutOverriding(): void
    {
        // backup logger
        $logger = ob_logger();
        // suppress logger
        ob_logger_register(new NullLogger());

        // we want fresh instance here, since it's a singleton, we use reflection to get a new instance
        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        // expect handle() to not throw any exception
        $this->expectNotToPerformAssertions();
        $instance->handle(new \Exception('test'));

        // restore logger
        ob_logger_register($logger);
    }
}
