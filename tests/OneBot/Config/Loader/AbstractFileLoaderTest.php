<?php

declare(strict_types=1);

namespace Tests\OneBot\Config\Loader;

use OneBot\Config\Loader\AbstractFileLoader;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AbstractFileLoaderTest extends TestCase
{
    /**
     * @dataProvider providerTestGetAbsolutePath
     */
    public function testGetAbsolutePath(string $file, string $base, string $expected, string $run_on): void
    {
        if ($run_on !== PHP_OS_FAMILY) {
            $this->markTestSkipped('This test is only for ' . $run_on);
        }
        $stub = $this->getMockForAbstractClass(AbstractFileLoader::class);
        $class = new \ReflectionClass($stub);
        $method = $class->getMethod('getAbsolutePath');
        $method->setAccessible(true);
        $path = $method->invoke($stub, $file, $base);
        $this->assertSame($expected, $path);
    }

    public function providerTestGetAbsolutePath(): array
    {
        return [
            'linux absolute path' => [
                '/etc/hosts',
                '/var/www',
                '/etc/hosts',
                'Linux',
            ],
            'linux relative path' => [
                'hosts',
                '/var/www',
                '/var/www/hosts',
                'Linux',
            ],
            'windows absolute path' => [
                'C:\\Windows\\System32\\drivers\\etc\\hosts',
                'C:\\Windows\\System32',
                'C:\\Windows\\System32\\drivers\\etc\\hosts',
                'Windows',
            ],
            'windows relative path' => [
                'drivers\\etc\\hosts',
                'C:\\Windows\\System32',
                'C:\\Windows\\System32\\drivers\\etc\\hosts',
                'Windows',
            ],
        ];
    }
}
