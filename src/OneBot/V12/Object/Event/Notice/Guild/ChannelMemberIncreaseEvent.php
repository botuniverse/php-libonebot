<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Notice\Guild;

use OneBot\V12\Object\Event\HasChannelId;
use OneBot\V12\Object\Event\HasOperatorId;

class ChannelMemberIncreaseEvent extends GuildNoticeEvent
{
    use HasChannelId;
    use HasOperatorId;

    public function __construct(string $sub_type, string $guild_id, string $channel_id, string $user_id, string $operator_id, $time = null)
    {
        parent::__construct('channel_member_increase', $sub_type, $user_id, $guild_id, $time);

        $this->channel_id = $channel_id;
        $this->operator_id = $operator_id;
    }
}
