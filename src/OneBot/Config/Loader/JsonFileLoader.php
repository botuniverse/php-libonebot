<?php

declare(strict_types=1);

namespace OneBot\Config\Loader;

class JsonFileLoader extends AbstractFileLoader
{
    /**
     * {@inheritDoc}
     */
    protected function loadFile(string $file)
    {
        try {
            return json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new LoadException("配置文件 '{$file}' 解析失败：{$e->getMessage()}");
        }
    }
}
