<?php

declare(strict_types=1);

namespace Illuminate\Notifications;

trait Notifiable
{
    use HasDatabaseNotifications;
    use RoutesNotifications;
}
