<?php

declare(strict_types=1);

namespace Illuminate\Tests\Integration\Foundation\Fixtures\EventDiscovery\UnionListeners;

use Illuminate\Tests\Integration\Foundation\Fixtures\EventDiscovery\Events\EventOne;
use Illuminate\Tests\Integration\Foundation\Fixtures\EventDiscovery\Events\EventTwo;

class UnionListener
{
    public function handle(EventOne|EventTwo $event)
    {

    }
}
