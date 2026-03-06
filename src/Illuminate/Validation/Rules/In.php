<?php

namespace Illuminate\Validation\Rules;

use Illuminate\Contracts\Support\Arrayable;
use Stringable;

use function Illuminate\Support\enum_value;

class In implements Stringable
{
    /**
     * The name of the rule.
     *
     * @var string
     */
    protected $rule = 'in';

    /**
     * The accepted values.
     */
    protected array $values;

    /**
     * Create a new in rule instance.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\UnitEnum|array|string  $values
     */
    public function __construct($values)
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $this->values = is_array($values) ? $values : func_get_args();
    }

    /**
     * Convert the rule to a validation string.
     *
     *
     * @see \Illuminate\Validation\ValidationRuleParser::parseParameters
     */
    public function __toString(): string
    {
        $values = array_map(function ($value): string {
            $value = enum_value($value);

            return '"'.str_replace('"', '""', $value).'"';
        }, $this->values);

        return $this->rule.':'.implode(',', $values);
    }
}
