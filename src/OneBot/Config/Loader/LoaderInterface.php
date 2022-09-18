<?php

declare(strict_types=1);

namespace OneBot\Config\Loader;

interface LoaderInterface
{
    /**
     * 加载配置，从指定来源获取配置内容，并返回解析后的数组
     *
     * @param mixed $source 配置来源
     *
     * @return array 配置数组
     */
    public function load($source): array;
}
