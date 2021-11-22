<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Message;

use OneBot\V12\Object\HasExtendedData;

/**
 * OneBot 默认扩展消息事件类
 */
class DefaultExtendedMessageEvent extends ExtendedMessageEvent
{
    use HasExtendedData;
}
