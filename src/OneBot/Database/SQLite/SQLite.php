<?php

declare(strict_types=1);

namespace OneBot\Database\SQLite;

/**
 * Class SQLite.
 */
class SQLite extends \PDO
{
    public function __construct()
    {
        parent::__construct('sqlite:' . __DIR__ . '/../../../../cache/db');
    }
}
