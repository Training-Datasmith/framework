<?php

declare(strict_types=1);

namespace Illuminate\Database\Eloquent\Factories;

use Illuminate\Support\Arr;

class CrossJoinSequence extends Sequence
{
    /**
     * Create a new cross join sequence instance.
     *
     * @param  array  ...$sequences
     */
    public function __construct(...$sequences)
    {
        $crossJoined = array_map(
            fn (array $a): array => array_merge(...$a),
            Arr::crossJoin(...$sequences),
        );

        parent::__construct(...$crossJoined);
    }
}
