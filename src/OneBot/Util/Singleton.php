<?php

declare(strict_types=1);

namespace OneBot\Util;

trait Singleton
{
    /**
     * 类实例
     *
     * @var static
     */
    protected static $instance;

    /**
     * 获取单例.
     *
     * @param mixed ...$args 初始化参数
     *
     * @return static
     */
    public static function getInstance(...$args): object
    {
        if (static::$instance === null) {
            // @phpstan-ignore-next-line
            static::$instance = new static(...$args);
        }
        return static::$instance;
    }
}
