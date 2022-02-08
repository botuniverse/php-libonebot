<?php

declare(strict_types=1);

namespace OneBot\Database\SQLite;

use OneBot\V12\Exception\OneBotException;
use PDO;

class SQLite extends PDO
{
    /**
     * 创建新的 SQLite 实例
     *
     * @throws OneBotException
     */
    public function __construct()
    {
        if (ob_config('lib.db', false)) {
            // TODO: 基于配置提供的路径创建数据库
            // TODO: 基于开发者项目路径创建数据库
            parent::__construct('sqlite:' . __DIR__ . '/../../../../cache/db');
        } else {
            throw new OneBotException('数据库支持未启用');
        }
    }
}
