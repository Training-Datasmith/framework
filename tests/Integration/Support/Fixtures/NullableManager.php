<?php

declare(strict_types=1);

namespace Illuminate\Tests\Integration\Support\Fixtures;

use Illuminate\Support\Manager;

class NullableManager extends Manager
{
    /**
     * Get the default driver name.
     *
     * @return string|null
     */
    public function getDefaultDriver()
    {

    }
}
