<?php

declare(strict_types=1);

namespace OneBot\V12\Config;

use PHPUnit\Framework\TestCase;

/**
 * @covers \OneBot\V12\Config\Config
 *
 * @internal
 */
class ConfigTest extends TestCase
{
    /**
     * @covers \OneBot\V12\Config\Config::get
     */
    public function testGetCanReturnExactValue(): void
    {
        $this->assertEquals('good', $this->getConfig()->get('top', 'bad'));
    }

    /**
     * @covers \OneBot\V12\Config\Config::get
     */
    public function testGetCanReturnDefaultValue(): void
    {
        $this->assertEquals('bad', $this->getConfig()->get('middle', 'bad'));
    }

    /**
     * @covers \OneBot\V12\Config\Config::get
     */
    public function testGetCanReturnDeepValue(): void
    {
        $this->assertEquals('good', $this->getConfig()->get('bottom.left', 'bad'));
    }

    private function getConfig(): Config
    {
        return new Config([
            'top' => 'good',
            'bottom' => [
                'left' => 'good',
                'right' => 'good',
            ],
        ]);
    }
}
