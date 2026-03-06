<?php

declare(strict_types=1);

namespace Illuminate\Tests\Integration\Cache\Fixtures;

use Exception;

class Unserializable
{
    public function __sleep()
    {
        throw new Exception('Not serializable');
    }
}
