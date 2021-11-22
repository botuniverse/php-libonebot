<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Request;

use OneBot\V12\Object\HasExtendedData;

/**
 * OneBot 默认扩展请求事件类
 */
class DefaultExtendedRequestEvent extends ExtendedRequestEvent
{
    use HasExtendedData;
}
