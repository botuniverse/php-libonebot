<?php

declare(strict_types=1);

namespace OneBot\Logger\Console;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ConsoleColorTest extends TestCase
{
    /**
     * @see https://github.com/botuniverse/php-libonebot/pull/32
     */
    public function testColor()
    {
        ob_start();
        echo ConsoleColor::apply(['rgb_135_0_255'], '█████');
        echo ConsoleColor::apply(['rgb_255_135_215'], '█████');
        echo ConsoleColor::apply(['rgb_20_120_255'], '█████');
        $content = ob_get_clean();
        $this->assertSame("\e[38;2;135;0;255m█████\e[0m\e[38;2;255;135;215m█████\e[0m\e[38;2;20;120;255m█████\e[0m", $content);
    }

    public function testAddStyle()
    {
        $cc = new ConsoleColor();
        $cc->red('asd');
        $this->assertSame("\033[31masd\033[0m", strval($cc));
    }
}
