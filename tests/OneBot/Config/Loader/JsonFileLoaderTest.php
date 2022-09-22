<?php

declare(strict_types=1);

namespace Tests\OneBot\Config\Loader;

use OneBot\Config\Loader\JsonFileLoader;
use OneBot\Config\Loader\LoadException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class JsonFileLoaderTest extends TestCase
{
    public function testLoadJsonFile(): void
    {
        $loader = new JsonFileLoader();
        $config = $loader->load('tests/Fixture/config.json');
        $this->assertSame('bar', $config['foo']);
    }

    public function testLoadInvalidJsonFile(): void
    {
        $this->expectException(LoadException::class);
        $loader = new JsonFileLoader();
        $loader->load('tests/Fixture/invalid.json');
    }
}
