<?php

declare(strict_types=1);

namespace Illuminate\Broadcasting\Broadcasters;

use Illuminate\Support\Str;

trait UsePusherChannelConventions
{
    /**
     * Return true if the channel is protected by authentication.
     *
     * @param  string  $channel
     */
    public function isGuardedChannel($channel): bool
    {
        return Str::startsWith($channel, ['private-', 'presence-']);
    }

    /**
     * Remove prefix from channel name.
     *
     * @param  string  $channel
     * @return string
     */
    public function normalizeChannelName($channel)
    {
        foreach (['private-encrypted-', 'private-', 'presence-'] as $prefix) {
            if (Str::startsWith($channel, $prefix)) {
                return Str::replaceFirst($prefix, '', $channel);
            }
        }

        return $channel;
    }
}
