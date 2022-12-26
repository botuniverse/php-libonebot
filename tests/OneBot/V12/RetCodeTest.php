<?php

declare(strict_types=1);

namespace Tests\OneBot\V12;

use OneBot\V12\RetCode;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class RetCodeTest extends TestCase
{
    public function testGetMessage()
    {
        $this->assertEquals('OK', RetCode::getMessage(0));
    }
}
