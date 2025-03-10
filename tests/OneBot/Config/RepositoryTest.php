<?php

declare(strict_types=1);

namespace Tests\OneBot\Config;

use OneBot\Config\Repository;
use OneBot\Config\RepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class RepositoryTest extends TestCase
{
    protected RepositoryInterface $repository;

    protected array $config;

    protected function setUp(): void
    {
        $this->repository = new Repository(
            $this->config = [
                'foo' => 'bar',
                'bar' => 'baz',
                'baz' => 'bat',
                'null' => null,
                'boolean' => true,
                'associate' => [
                    'x' => 'xxx',
                    'y' => 'yyy',
                ],
                'array' => [
                    'aaa',
                    'zzz',
                ],
                'x' => [
                    'z' => 'zoo',
                ],
                'a.b' => 'c',
                'a' => [
                    'b.c' => 'd',
                ],
                'default' => 'yes',
                'another array' => [
                    'foo', 'bar',
                ],
            ],
        );
    }

    // 尚未确定是否应该支持
    //    public function testGetValueWhenKeyContainsDot(): void
    //    {
    //        $this->assertEquals('c', $this->repository->get('a.b'));
    //        $this->assertEquals('d', $this->repository->get('a.b.c'));
    //    }

    public function testGetBooleanValue(): void
    {
        $this->assertTrue($this->repository->get('boolean'));
    }

    /**
     * @dataProvider providerTestGetValue
     * @param mixed $expected
     */
    public function testGetValue(string $key, $expected): void
    {
        $this->assertSame($expected, $this->repository->get($key));
    }

    public function providerTestGetValue(): array
    {
        return [
            'null' => ['null', null],
            'boolean' => ['boolean', true],
            'associate' => ['associate', ['x' => 'xxx', 'y' => 'yyy']],
            'array' => ['array', ['aaa', 'zzz']],
            'dot access' => ['x.z', 'zoo'],
        ];
    }

    public function testGetWithDefault(): void
    {
        $this->assertSame('default', $this->repository->get('not_exist', 'default'));
        $this->assertSame('default', $this->repository->get('deep.not_exists', 'default'));
    }

    public function testSetValue(): void
    {
        $this->repository->set('key', 'value');
        $this->assertSame('value', $this->repository->get('key'));
    }

    public function testSetArrayValue(): void
    {
        $this->repository->set('array', ['a', 'b']);
        $this->assertSame(['a', 'b'], $this->repository->get('array'));
        $this->assertSame('a', $this->repository->get('array.0'));
    }

    /**
     * @dataProvider providerTestDeleteValue
     */
    public function testDeleteValue(string $key): void
    {
        $this->repository->set($key, null);
        $this->assertNull($this->repository->get($key));
    }

    public function providerTestDeleteValue(): array
    {
        return [
            'shallow' => ['foo'],
            'deep' => ['associate.x'],
            'not exists' => ['not_exists'],
            'not exists deep' => ['deep.not_exists'],
        ];
    }

    public function testHas(): void
    {
        $this->assertTrue($this->repository->has('foo'));
        $this->assertFalse($this->repository->has('not_exist'));
    }

    public function testAll(): void
    {
        $this->assertSame($this->config, $this->repository->all());
    }
}
