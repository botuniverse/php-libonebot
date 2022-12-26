<?php

declare(strict_types=1);

namespace Tests\OneBot\Util;

use OneBot\Util\FileUtil;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class FileUtilTest extends TestCase
{
    public function testRemoveDirRecursive()
    {
        mkdir(getcwd() . '/data/help1/help2', 0755, true);
        touch(getcwd() . '/data/help1/help2/asd');
        touch(getcwd() . '/data/help1/asdasd');
        $this->assertTrue(FileUtil::removeDirRecursive(getcwd() . '/data/help1'));
        $this->assertFalse(FileUtil::removeDirRecursive(getcwd() . '/data/help1'));
    }
}
