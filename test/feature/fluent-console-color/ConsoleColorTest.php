<?php

declare(strict_types=1);

namespace OneBot\Logger\Console;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 * @see https://github.com/botuniverse/php-libonebot/pull/32
 */
class ConsoleColorTest extends TestCase
{
    public function testClassicColorCanOutput(): void
    {
        $output = ConsoleColor::red('test')->__toString();
        $this->assertSame("\033[31mtest\033[0m", $output);
    }

    public function test256ColorCanOutput(): void
    {
        $output = ConsoleColor::apply(['color_211'], 'test')->__toString();
        $this->assertSame("\033[38;5;211mtest\033[0m", $output);
    }

    public function testRGBColorCanOutput(): void
    {
        $output = ConsoleColor::apply(['rgb_135_0_255'], 'test')->__toString();
        $this->assertSame("\033[38;2;135;0;255mtest\033[0m", $output);
    }

    public function testStyleCanApplyFluently(): void
    {
        $output = ConsoleColor::red('test')->bold()->italic()->__toString();
        $this->assertSame("\033[31;1;3mtest\033[0m", $output);
    }

    public function testStyleCanCombine(): void
    {
        $output = ConsoleColor::red('test')->green()->__toString();
        $this->assertSame("\033[31;32mtest\033[0m", $output);
    }
}
