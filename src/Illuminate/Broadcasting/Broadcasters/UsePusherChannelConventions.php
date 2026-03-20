<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting\Broadcasters;

use Illuminate\Support\Str;
trait Use_Pusher_Channel_Conventions
{
    /**
     * Return true if the channel is protected by authentication.
     *
     * @param  string  $channel
     */
    public function is_guarded_channel($channel): bool
    {
        return Str::starts_with($channel, ['private-', 'presence-']);
    }
    /**
     * Remove prefix from channel name.
     *
     * @param  string  $channel
     * @return string
     */
    public function normalize_channel_name($channel)
    {
        foreach (['private-encrypted-', 'private-', 'presence-'] as $prefix) {
            if (Str::starts_with($channel, $prefix)) {
                return Str::replace_first($prefix, '', $channel);
            }
        }
        return $channel;
    }
}