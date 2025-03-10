<?php

declare(strict_types=1);

namespace OneBot\Config;

class Repository implements RepositoryInterface
{
    /**
     * @var array 配置项
     */
    protected array $config = [];

    /**
     * 构造新的配置类
     *
     * @param array $config 配置项
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

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

    public function set(string $key, $value): void
    {
        if ($value === null) {
            $this->delete($key);
            return;
        }

        $data = &$this->config;

        // 找到对应的插入位置，并确保前置数组存在
        foreach (explode('.', $key) as $segment) {
            if (!isset($data[$segment]) || !is_array($data[$segment])) {
                $data[$segment] = [];
            }

            $data = &$data[$segment];
        }

        $data = $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function all(): array
    {
        return $this->config;
    }

    /**
     * 删除指定配置项
     *
     * @param string $key 键名，使用.分割多维数组
     * @internal
     */
    private function delete(string $key): void
    {
        if (array_key_exists($key, $this->config)) {
            unset($this->config[$key]);
            return;
        }

        $data = &$this->config;
        $segments = explode('.', $key);
        $lastSegment = array_pop($segments);

        foreach ($segments as $segment) {
            if (!isset($data[$segment]) || !is_array($data[$segment])) {
                return;
            }

            $data = &$data[$segment];
        }

        unset($data[$lastSegment]);
    }
}
