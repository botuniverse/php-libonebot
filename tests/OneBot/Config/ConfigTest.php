<?php

declare(strict_types=1);

namespace Tests\OneBot\Config;

use OneBot\Config\Config;
use OneBot\Config\Loader\JsonFileLoader;
use OneBot\Config\Repository;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ConfigTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        file_put_contents(__DIR__ . '/config_mock.json', json_encode(['foo' => 'bar', 'array' => ['aaa', 'zzz']]));
    }

    public static function tearDownAfterClass(): void
    {
        unlink(__DIR__ . '/config_mock.json');
    }

    public function testLoad(): void
    {
        $config = new Config();
        $config->load(__DIR__ . '/config_mock.json', new JsonFileLoader());
        $this->assertSame('bar', $config->getRepository()->get('foo'));
        $this->assertSame(['aaa', 'zzz'], $config->getRepository()->get('array'));
    }

    public function testCanReplaceRepository(): void
    {
        $config = new Config();
        $this->assertNull($config->get('foo'));
        $config->setRepository(new Repository(['foo' => 'bar']));
        $this->assertSame('bar', $config->get('foo'));
    }

    /**
     * @dataProvider providerTestConstruct
     * @param mixed $context
     */
    public function testConstruct($context): void
    {
        $config = new Config($context);
        $this->assertSame('bar', $config->get('foo'));
    }

    public function providerTestConstruct(): array
    {
        return [
            'array' => [
                ['foo' => 'bar'],
            ],
            'path' => [
                __DIR__ . '/config_mock.json',
            ],
            'repository' => [
                new Repository(['foo' => 'bar']),
            ],
        ];
    }
}
