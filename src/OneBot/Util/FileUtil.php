<?php

declare(strict_types=1);

namespace OneBot\Util;

class FileUtil
{
    /**
     * 检查路径是否为相对路径（根据第一个字符是否为"/"来判断）
     *
     * @param  string $path 路径
     * @return bool   返回结果
     * @since 2.5
     */
    public static function isRelativePath(string $path): bool
    {
        // 适配 Windows 的多盘符目录形式
        if (DIRECTORY_SEPARATOR === '\\') {
            return !(strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':');
        }
        return strlen($path) > 0 && $path[0] !== '/';
    }

    /**
     * 根据路径和操作系统选择合适的分隔符，用于适配 Windows 和 Linux
     *
     * @param string $path 路径
     */
    public static function getRealPath(string $path): string
    {
        if (strpos($path, 'phar://') === 0) {
            return $path;
        }
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * 递归或非递归扫描目录，可返回相对目录的文件列表或绝对目录的文件列表
     *
     * @param  string      $dir         目录
     * @param  bool        $recursive   是否递归扫描子目录
     * @param  bool|string $relative    是否返回相对目录，如果为true则返回相对目录，如果为false则返回绝对目录
     * @param  bool        $include_dir 非递归模式下，是否包含目录
     * @return array|false
     * @since 2.5
     */
    public static function scanDirFiles(string $dir, bool $recursive = true, $relative = false, bool $include_dir = false)
    {
        $dir = self::getRealPath($dir);
        // 不是目录不扫，直接 false 处理
        if (!is_dir($dir)) {
            ob_logger_registered() && ob_logger()->warning('扫描目录失败，目录不存在');
            return false;
        }
        ob_logger_registered() && ob_logger()->debug('扫描' . $dir);
        // 套上 zm_dir
        $scan_list = scandir($dir);
        if ($scan_list === false) {
            ob_logger_registered() && ob_logger()->warning('扫描目录失败，目录无法读取: ' . $dir);
            return false;
        }
        $list = [];
        // 将 relative 置为相对目录的前缀
        if ($relative === true) {
            $relative = $dir;
        }
        // 遍历目录
        foreach ($scan_list as $v) {
            // Unix 系统排除这俩目录
            if ($v == '.' || $v == '..') {
                continue;
            }
            $sub_file = self::getRealPath($dir . '/' . $v);
            if (is_dir($sub_file) && $recursive) {
                # 如果是 目录 且 递推 , 则递推添加下级文件
                $list = array_merge($list, self::scanDirFiles($sub_file, $recursive, $relative));
            } elseif (is_file($sub_file) || is_dir($sub_file) && !$recursive && $include_dir) {
                # 如果是 文件 或 (是 目录 且 不递推 且 包含目录)
                if (is_string($relative) && mb_strpos($sub_file, $relative) === 0) {
                    $list[] = ltrim(mb_substr($sub_file, mb_strlen($relative)), '/\\');
                } elseif ($relative === false) {
                    $list[] = $sub_file;
                }
            }
        }
        return $list;
    }

    public static function removeDirRecursive(string $dir): bool
    {
        $dir = self::getRealPath($dir);
        // 不是目录不扫，直接 false 处理
        if (!is_dir($dir)) {
            return false;
        }
        // 套上 zm_dir
        $scan_list = scandir($dir);
        if ($scan_list === false) {
            return false;
        }
        // 遍历目录
        $has_file = false;
        foreach ($scan_list as $v) {
            // Unix 系统排除这俩目录
            if ($v == '.' || $v == '..') {
                continue;
            }
            $has_file = true;
            $sub_file = self::getRealPath($dir . '/' . $v);
            if (is_dir($sub_file)) {
                if (!self::removeDirRecursive($sub_file)) {
                    return false;
                }
            } else {
                if (!unlink($sub_file)) {
                    return false;
                }
            }
        }
        rmdir($dir);
        return true;
    }

    public static function mkdir(string $dir, $perm = 0755, bool $recursive = false): bool
    {
        if (!is_dir($dir)) {
            return \mkdir($dir, $perm, $recursive);
        }
        return true;
    }

    public static function saveMetaFile(string $path, string $file_id, $data, array $config): bool
    {
        if (!self::mkdir($path, 0755, true)) {
            ob_logger_registered() && ob_logger()->error('无法保存文件，因为无法创建目录: ' . $path);
            return false;
        }
        $file_path = self::getRealPath($path . '/' . $file_id);
        if ($data !== null && file_put_contents($file_path, $data) === false) {
            ob_logger_registered() && ob_logger()->error('无法保存文件，因为无法写入文件: ' . $file_path);
            return false;
        }
        if (!isset($config['name'])) {
            ob_logger_registered() && ob_logger()->error('无法保存文件，因为元数据缺少文件名: ' . $file_path);
            return false;
        }
        if ($data === null && !file_exists($file_path)) {
            $config['nodata'] = true;
        }
        if (!isset($config['nodata']) && ($config['sha256'] ?? null) !== null) {
            $data = is_null($data) ? file_get_contents($file_path) : (is_object($data) ? strval($data) : $data);
            if (hash('sha256', $data) !== $config['sha256']) {
                ob_logger_registered() && ob_logger()->error('无法保存文件，sha256值不匹配！');
                return false;
            }
        }
        $conf = json_encode($config);
        if (file_put_contents($file_path . '.json', $conf) === false) {
            ob_logger_registered() && ob_logger()->error('无法保存文件，因为无法写入文件: ' . $file_path . '.json');
            return false;
        }
        return true;
    }

    public static function getMetaFile(string $path, string $file_id): array
    {
        $file_path = self::getRealPath($path . '/' . $file_id);
        if (!file_exists($file_path . '.json')) {
            ob_logger_registered() && ob_logger()->error('无法读取文件，因为元数据或文件不存在: ' . $file_path);
            return [null, null];
        }
        $data = json_decode(file_get_contents($file_path . '.json'), true);
        if (!isset($data['name'])) {
            ob_logger_registered() && ob_logger()->error('无法读取文件，因为元数据缺少文件名: ' . $file_path);
            return [null, null];
        }
        if (!file_exists($file_path)) {
            $content = null;
        } else {
            $content = file_get_contents($file_path);
            if ($content === false) {
                $content = null;
            }
        }
        return [$data, $content];
    }
}
