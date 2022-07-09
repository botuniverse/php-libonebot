<?php

declare(strict_types=1);

namespace Tests\OneBot;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class GlobalDefinesTest extends TestCase
{
    public function testObUuidgen()
    {
        $this->assertIsString(ob_uuidgen());
        $this->assertEquals(36, strlen(ob_uuidgen()));
    }
}
