<?php

declare(strict_types=1);

namespace Tests\OneBot\Config\Loader;

use OneBot\Config\Loader\DelegateLoader;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DelegateLoaderTest extends TestCase
{
    public function testConstructWithInvalidLoader(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        new DelegateLoader([new \stdClass()]);
    }

    public function testDetermineUnknownLoader(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('无法确定加载器，未知的配置来源：foo');
        $class = new \ReflectionClass(DelegateLoader::class);
        $method = $class->getMethod('determineLoader');
        $method->setAccessible(true);
        $method->invoke(new DelegateLoader([]), 'foo');
    }
}
