<?php

declare(strict_types=1);

namespace Illuminate\Tests\Integration\Foundation\Fixtures\EventDiscovery\Listeners;

use Illuminate\Tests\Integration\Foundation\Fixtures\EventDiscovery\Events\EventOne;

interface ListenerInterface
{
    public function handle(EventOne $event);
}
