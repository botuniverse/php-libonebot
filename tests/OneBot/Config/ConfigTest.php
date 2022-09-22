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
    public function testLoad(): void
    {
        $config = new Config();
        $config->load('tests/Fixture/config.json', new JsonFileLoader());
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
                'tests/Fixture/config.json',
            ],
            'repository' => [
                new Repository(['foo' => 'bar']),
            ],
        ];
    }
}
