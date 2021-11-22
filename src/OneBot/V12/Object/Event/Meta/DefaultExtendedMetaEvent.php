<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Meta;

use OneBot\V12\Object\HasExtendedData;

/**
 * OneBot 默认扩展元事件类
 */
class DefaultExtendedMetaEvent extends ExtendedMetaEvent
{
    use HasExtendedData;
}
