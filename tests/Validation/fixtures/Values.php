<?php

declare(strict_types=1);

namespace Illuminate\Tests\Validation\fixtures;

use Illuminate\Contracts\Support\Arrayable;

class Values implements Arrayable
{
    public function toArray()
    {
        return [1, 2, 3, 4];
    }
}
