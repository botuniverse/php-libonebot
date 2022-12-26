<?php

declare(strict_types=1);

namespace Tests\OneBot\Config\Loader;

use OneBot\Config\Loader\AbstractFileLoader;
use OneBot\Config\Loader\LoadException;
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

    public function testLoad(): void
    {
        $stub = $this->getMockForAbstractClass(AbstractFileLoader::class);
        $stub->method('loadFile')
            ->willReturn(['foo' => 'bar']);
        $this->assertSame(['foo' => 'bar'], $stub->load('composer.json'));
    }

    public function testLoadWithException(): void
    {
        $exception = new \Exception('test');
        $this->expectExceptionObject($exception);
        $stub = $this->getMockForAbstractClass(AbstractFileLoader::class);
        $stub->method('loadFile')
            ->willThrowException($exception);
        $stub->load('composer.json');
    }

    public function testLoadNotExistsFile(): void
    {
        $this->expectException(LoadException::class);
        $this->expectExceptionMessageMatches('/^配置文件 \'[^\']+\' 不存在或不可读$/');
        $stub = $this->getMockForAbstractClass(AbstractFileLoader::class);
        $stub->load('not_exists_file');
    }

    public function testLoadWithInvalidData(): void
    {
        $this->expectException(LoadException::class);
        $this->expectExceptionMessageMatches('/^配置文件 \'[^\']+\' 加载失败$/');
        $stub = $this->getMockForAbstractClass(AbstractFileLoader::class);
        $stub->method('loadFile')
            ->willReturn(false);
        $stub->load('composer.json');
    }

    public function testLoadWithInvalidDataAgain(): void
    {
        $this->expectException(LoadException::class);
        $this->expectExceptionMessageMatches('/^配置文件 \'[^\']+\' 加载失败：配置必须为关联数组或对象$/');
        $stub = $this->getMockForAbstractClass(AbstractFileLoader::class);
        $stub->method('loadFile')
            ->willReturn([1, 2, 3, 4]);
        $stub->load('composer.json');
    }
}
