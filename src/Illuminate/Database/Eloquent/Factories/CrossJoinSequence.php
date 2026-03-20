<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Factories;

use Illuminate\Support\Arr;
class Cross_Join_Sequence extends Sequence
{
    /**
     * Create a new cross join sequence instance.
     *
     * @param  array  ...$sequences
     */
    public function __construct(...$sequences)
    {
        $cross_joined = array_map(fn(array $a): array => array_merge(...$a), Arr::cross_join(...$sequences));
        parent::__construct(...$cross_joined);
    }
}