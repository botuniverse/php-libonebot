<?php

declare(strict_types=1);

namespace OneBot\Config\Loader;

class DelegateLoader implements LoaderInterface
{
    /**
     * @var array{string: LoaderInterface} 加载器列表
     */
    protected array $loaders;

    /**
     * @param null|array{string: LoaderInterface} $loaders 加载器列表，null则使用默认列表
     */
    public function __construct(array $loaders = null)
    {
        foreach ((array) $loaders as $key => $loader) {
            if (!$loader instanceof LoaderInterface) {
                throw new \UnexpectedValueException("加载器 {$key} 不是有效的加载器，必须实现 LoaderInterface 接口");
            }
        }

        $this->loaders = $loaders ?? self::getDefaultLoaders();
    }

    public function load($source): array
    {
        return $this->determineLoader($source)->load($source);
    }

    public static function getDefaultLoaders(): array
    {
        return [
            'json' => new JsonFileLoader(),
        ];
    }

    protected function determineLoader($source): LoaderInterface
    {
        $key = is_dir($source) ? 'dir' : pathinfo($source, PATHINFO_EXTENSION);

        if (!isset($this->loaders[$key])) {
            throw new \UnexpectedValueException("无法确定加载器，未知的配置来源：{$source}");
        }

        return $this->loaders[$key];
    }
}
