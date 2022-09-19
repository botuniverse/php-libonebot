<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Notice\Guild;

use OneBot\V12\Object\Event\HasGuildId;
use OneBot\V12\Object\Event\Notice\NoticeEvent;

abstract class GuildNoticeEvent extends NoticeEvent
{
    use HasGuildId;

    public function __construct(string $detail_type, string $sub_type, string $guild_id, $time = null)
    {
        parent::__construct($detail_type, $sub_type, $time);

        $this->guild_id = $guild_id;
    }
}
