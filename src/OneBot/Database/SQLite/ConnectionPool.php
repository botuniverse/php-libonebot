<?php

declare(strict_types=1);

namespace OneBot\Database\SQLite;

use OneBot\ObjectPool\AbstractObjectPool;
use OneBot\Util\Singleton;

/**
 * Class ConnectionPool.
 */
class ConnectionPool extends AbstractObjectPool
{
    use Singleton;

    protected function makeObject(): object
    {
        return new SQLite();
    }
}
