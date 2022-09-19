<?php

declare(strict_types=1);

namespace OneBot\Config;

use OneBot\Config\Loader\DelegateLoader;
use OneBot\Config\Loader\LoaderInterface;

class Config
{
    /**
     * @var RepositoryInterface 配置仓库
     */
    protected RepositoryInterface $repository;

    /**
     * 构造新的配置对象
     *
     * @param null|array|RepositoryInterface|string $context 配置数组、配置文件路径或是配置仓库，留空表示后续手动设置
     */
    public function __construct($context = null)
    {
        switch (true) {
            case is_array($context):
                $this->repository = new Repository($context);
                break;
            case is_string($context):
                $this->repository = new Repository();
                $this->load($context, new DelegateLoader());
                break;
            case $context instanceof RepositoryInterface:
                $this->repository = $context;
                break;
            default:
                $this->repository = new Repository();
        }
    }

    /**
     * 获取配置仓库
     */
    public function getRepository(): RepositoryInterface
    {
        return $this->repository;
    }

    /**
     * 设置配置仓库
     */
    public function setRepository(RepositoryInterface $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * 加载配置
     *
     * @param mixed           $context 传递给加载器的上下文，通常是文件路径
     * @param LoaderInterface $loader  指定的加载器
     */
    public function load($context, LoaderInterface $loader): void
    {
        $data = $loader->load($context);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->merge($key, $value);
            } else {
                $this->set($key, $value);
            }
        }
    }

    /**
     * 合并传入的配置数组至指定的配置项
     *
     * 请注意内部实现是 array_replace_recursive，而不是 array_merge
     *
     * @param string $key    目标配置项，必须为数组
     * @param array  $config 要合并的配置数组
     */
    public function merge(string $key, array $config): void
    {
        $original = $this->get($key, []);
        $this->set($key, array_replace_recursive($original, $config));
    }

    /**
     * 获取配置项
     *
     * @param  string           $key     键名，使用.分割多维数组
     * @param  mixed            $default 默认值
     * @return null|array|mixed
     *
     * @codeCoverageIgnore 已在 RepositoryTest 中测试
     */
    public function get(string $key, $default = null)
    {
        return $this->repository->get($key, $default);
    }

    /**
     * 设置配置项
     *
     * @param string     $key   键名，使用.分割多维数组
     * @param null|mixed $value 值，null表示删除
     *
     * @codeCoverageIgnore 已在 RepositoryTest 中测试
     */
    public function set(string $key, $value): void
    {
        $this->repository->set($key, $value);
    }

    /**
     * 判断配置项是否存在
     *
     * @param  string $key 键名，使用.分割多维数组
     * @return bool   是否存在
     *
     * @codeCoverageIgnore 已在 RepositoryTest 中测试
     */
    public function has(string $key): bool
    {
        return $this->repository->has($key);
    }

    /**
     * 获取所有配置项
     *
     * @codeCoverageIgnore 已在 RepositoryTest 中测试
     */
    public function all(): array
    {
        return $this->repository->all();
    }
}
