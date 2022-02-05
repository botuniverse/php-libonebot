<?php

declare(strict_types=1);

namespace OneBot\V12\Config;

interface ConfigInterface
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
     * 获取启用的通讯方式
     */
    public function getEnabledCommunications(): array;
}
