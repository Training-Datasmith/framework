<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Database\Query;

use Illuminate\Database\Grammar;
interface Expression
{
    /**
     * Get the value of the expression.
     *
     * @return string|int|float
     */
    public function get_value(Grammar $grammar);
}