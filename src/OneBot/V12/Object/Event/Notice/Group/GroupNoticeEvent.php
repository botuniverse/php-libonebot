<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Notice\Group;

use OneBot\V12\Object\Event\HasGroupId;
use OneBot\V12\Object\Event\Notice\NoticeEvent;

abstract class GroupNoticeEvent extends NoticeEvent
{
    use HasGroupId;

    public function __construct(string $detail_type, string $sub_type, string $group_id, $time = null)
    {
        parent::__construct($detail_type, $sub_type, $time);

        $this->group_id = $group_id;
    }
}
