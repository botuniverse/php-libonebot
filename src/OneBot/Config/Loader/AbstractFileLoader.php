<?php

declare(strict_types=1);

namespace OneBot\Config\Loader;

use OneBot\Util\Utils;
use stdClass;

abstract class AbstractFileLoader implements LoaderInterface
{
    /**
     * {@inheritDoc}
     */
    public function load($source): array
    {
        // TODO: flexible base path
        $file = $this->getAbsolutePath($source, getcwd());
        $this->ensureFileExists($file);

        try {
            $data = $this->loadFile($file);
        } catch (\Throwable $e) {
            throw new LoadException("配置文件 '{$file}' 加载失败：{$e->getMessage()}", 0, $e);
        }
        $this->ensureDataLoaded($data, $file);

        return [$this->getConfigPrefix($file) => (array) $data];
    }

    /**
     * 从文件加载配置
     *
     * @param  string               $file 文件路径（绝对路径）
     * @return array|mixed|stdClass 配置数组、对象或者其他类型，但其最终必须可以被转换为数组，可以直接返回null或false代表失败
     */
    abstract protected function loadFile(string $file);

    /**
     * 获取文件的绝对路径
     *
     * @param string $file 文件路径（相对路径）
     * @param string $base 基础路径
     */
    protected function getAbsolutePath(string $file, string $base): string
    {
        // From: https://github.com/zhamao-robot/zhamao-framework/blob/10a0ee91427f4d5989ebd8784e533beefdac6e89/src/ZM/Store/FileSystem.php
        // 适配 Windows 的多盘符目录形式
        if (DIRECTORY_SEPARATOR === '\\') {
            $is_relative = strlen($file) > 2 && ctype_alpha($file[0]) && $file[1] === ':';
        } else {
            $is_relative = $file !== '' && $file[0] !== '/';
        }

        return $is_relative ? $base . DIRECTORY_SEPARATOR . $file : $file;
    }

    protected function ensureFileExists(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new LoadException("配置文件 '{$file}' 不存在或不可读");
        }
    }

    protected function ensureDataLoaded($data, string $file): void
    {
        if ($data === false || $data === null) {
            throw new LoadException("配置文件 '{$file}' 加载失败");
        }

        if (!$data instanceof stdClass && !Utils::isAssocArray($data)) {
            throw new LoadException("配置文件 '{$file}' 加载失败：配置必须为关联数组或对象");
        }
    }

    protected function getConfigPrefix(string $file): string
    {
        return pathinfo($file, PATHINFO_FILENAME);
    }
}
