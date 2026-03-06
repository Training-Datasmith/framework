<?php

namespace Illuminate\Notifications;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * @template TKey of array-key
 * @template TModel of DatabaseNotification
 *
 * @extends \Illuminate\Database\Eloquent\Collection<TKey, TModel>
 */
class DatabaseNotificationCollection extends EloquentCollection
{
    /**
     * Mark all notifications as read.
     */
    public function markAsRead(): void
    {
        $this->each->markAsRead();
    }

    /**
     * Mark all notifications as unread.
     */
    public function markAsUnread(): void
    {
        $this->each->markAsUnread();
    }
}
