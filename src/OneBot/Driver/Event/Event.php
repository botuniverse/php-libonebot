<?php

declare(strict_types=1);

namespace OneBot\Driver\Event;

interface Event
{
    public const EVENT_WORKER_START = 'worker.start';

    public const EVENT_WORKER_STOP = 'worker.stop';

    public const EVENT_HTTP_REQUEST = 'http.request';

    public const EVENT_WEBSOCKET_MESSAGE = 'websocket.message';

    public const EVENT_WEBSOCKET_OPEN = 'websocket.open';

    public const EVENT_WEBSOCKET_CLOSE = 'websocket.close';

    public const EVENT_MASTER_START = 'master.start';

    public const EVENT_MANAGER_START = 'manager.start';

    public const EVENT_USER_PROCESS_START = 'user.process.start';

    public const EVENT_DRIVER_INIT = 'driver.init';

    public const EVENT_UNKNOWN = 'unknown';
}
