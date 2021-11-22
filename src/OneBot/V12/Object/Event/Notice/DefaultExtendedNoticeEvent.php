<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Notice;

use OneBot\V12\Object\HasExtendedData;

/**
 * OneBot 默认扩展通知事件类
 */
class DefaultExtendedNoticeEvent extends ExtendedNoticeEvent
{
    use HasExtendedData;
}
