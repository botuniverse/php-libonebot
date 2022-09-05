<?php

declare(strict_types=1);

namespace OneBot\V12\Object\Event\Message;

use OneBot\V12\Object\Event\HasChannelId;
use OneBot\V12\Object\Event\HasGuildId;

/**
 * 频道消息事件
 */
class ChannelMessageEvent extends MessageEvent
{
    use HasGuildId;
    use HasChannelId;

    public function __construct(string $guild_id, string $channel_id, string $user_id, $message, $time = null)
    {
        parent::__construct('channel', $user_id, $message, $time);

        $this->guild_id = $guild_id;
        $this->channel_id = $channel_id;
    }
}
