<?php

declare(strict_types=1);

namespace Illuminate\Validation\Rules;

use Illuminate\Support\Traits\Conditionable;
use Stringable;

class Exists implements Stringable
{
    use Conditionable;
    use DatabaseRule;

    /**
     * Convert the rule to a validation string.
     */
    public function __toString(): string
    {
        return rtrim(sprintf(
            'exists:%s,%s,%s',
            $this->table,
            $this->column,
            $this->formatWheres()
        ), ',');
    }
}
