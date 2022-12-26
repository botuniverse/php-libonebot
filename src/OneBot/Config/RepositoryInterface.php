<?php

declare(strict_types=1);

namespace OneBot\Config;

interface RepositoryInterface
{
    /**
     * 获取配置项
     *
     * @param  string           $key     键名，使用.分割多维数组
     * @param  mixed            $default 默认值
     * @return null|array|mixed
     */
    public function get(string $key, $default = null);

    /**
     * 设置配置项
     *
     * @param string     $key   键名，使用.分割多维数组
     * @param null|mixed $value 值，null表示删除
     */
    public function set(string $key, $value): void;

    /**
     * 判断配置项是否存在
     *
     * @param  string $key 键名，使用.分割多维数组
     * @return bool   是否存在
     */
    public function has(string $key): bool;

    /**
     * 获取所有配置项
     */
    public function all(): array;
}
