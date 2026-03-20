<?php

declare (strict_types=1);
namespace Illuminate\Database\Query;

use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Grammar;
/**
 * @template TValue of string|int|float
 */
class Expression implements Expression_Contract
{
    /**
     * Create a new raw query expression.
     *
     * @param  TValue  $value
     */
    public function __construct(protected $value)
    {
    }
    /**
     * Get the value of the expression.
     *
     * @return TValue
     */
    public function get_value(Grammar $grammar)
    {
        return $this->value;
    }
}