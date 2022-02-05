<?php

declare(strict_types=1);

namespace OneBot\V12\Config;

class Config implements ConfigInterface
{
    /**
     * @var array
     */
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, $default = null)
    {
        // 在表层直接查找，找到就直接返回
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        // 判断是否包含.，即是否读取多维数组，否则代表没有对应数据
        if (strpos($key, '.') === false) {
            return $default;
        }

        // 在多维数组中查找
        $data = $this->config;
        foreach (explode('.', $key) as $segment) {
            // $data不是数组表示没有下级元素
            // $segment不在数组中表示没有对应数据
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }

            $data = &$data[$segment];
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function getEnabledCommunications(): array
    {
        return $this->get('communications', []);
    }
}
