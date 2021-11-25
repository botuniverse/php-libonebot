<?php

declare(strict_types=1);

namespace OneBot\Database\SQLite;

use OneBot\ObjectPool\AbstractObjectPool;

/**
 * Class ConnectionPool.
 */
class ConnectionPool extends AbstractObjectPool
{
    protected function makeObject(): object
    {
        return new SQLite();
    }
}
