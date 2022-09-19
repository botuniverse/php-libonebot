<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Notice\Guild;

use OneBot\V12\Object\Event\HasChannelId;
use OneBot\V12\Object\Event\HasMessageId;
use OneBot\V12\Object\Event\HasOperatorId;
use OneBot\V12\Object\Event\HasUserId;

class ChannelMessageDeleteEvent extends GuildNoticeEvent
{
    use HasChannelId;
    use HasOperatorId;
    use HasMessageId;
    use HasUserId;

    public function __construct(string $sub_type, string $guild_id, string $channel_id, string $message_id, string $user_id, string $operator_id, $time = null)
    {
        parent::__construct('channel_message_delete', $sub_type, $guild_id, $time);

        $this->channel_id = $channel_id;
        $this->operator_id = $operator_id;
        $this->message_id = $message_id;
        $this->user_id = $user_id;
    }
}
