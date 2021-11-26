<?php

declare(strict_types=1);

namespace OneBot\Database\SQLite;

use OneBot\V12\Exception\OneBotException;

/**
 * Class SQLite.
 */
class SQLite extends \PDO
{
    public function __construct()
    {
        if (config('lib.db', false)) {
            parent::__construct('sqlite:' . __DIR__ . '/../../../../cache/db');
        } else {
            throw new OneBotException('数据库支持未启用');
        }
    }
}
