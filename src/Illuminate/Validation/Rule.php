<?php

declare(strict_types=1);

namespace Illuminate\Validation;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\Rules\AnyOf;
use Illuminate\Validation\Rules\ArrayRule;
use Illuminate\Validation\Rules\Can;
use Illuminate\Validation\Rules\Date;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\ImageFile;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Validation\Rules\Numeric;
use Illuminate\Validation\Rules\ProhibitedIf;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;

class Rule
{
    use Macroable;

    /**
     * Get a can constraint builder instance.
     *
     * @param  string  $ability
     * @param  mixed  ...$arguments
     */
    public static function can($ability, ...$arguments): \Illuminate\Validation\Rules\Can
    {
        return new Can($ability, $arguments);
    }

    /**
     * Apply the given rules if the given condition is truthy.
     *
     * @param  callable|bool  $condition
     * @param  \Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Contracts\Validation\InvokableRule|\Illuminate\Contracts\Validation\Rule|\Closure|array|string  $rules
     * @param  \Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Contracts\Validation\InvokableRule|\Illuminate\Contracts\Validation\Rule|\Closure|array|string  $defaultRules
     */
    public static function when($condition, $rules, $defaultRules = []): \Illuminate\Validation\ConditionalRules
    {
        return new ConditionalRules($condition, $rules, $defaultRules);
    }

    /**
     * Apply the given rules if the given condition is falsy.
     *
     * @param  callable|bool  $condition
     * @param  \Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Contracts\Validation\InvokableRule|\Illuminate\Contracts\Validation\Rule|\Closure|array|string  $rules
     * @param  \Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Contracts\Validation\InvokableRule|\Illuminate\Contracts\Validation\Rule|\Closure|array|string  $defaultRules
     */
    public static function unless($condition, $rules, $defaultRules = []): \Illuminate\Validation\ConditionalRules
    {
        return new ConditionalRules($condition, $defaultRules, $rules);
    }

    /**
     * Get an array rule builder instance.
     *
     * @param  array|null  $keys
     */
    public static function array($keys = null): \Illuminate\Validation\Rules\ArrayRule
    {
        return new ArrayRule(...func_get_args());
    }

    /**
     * Create a new nested rule set.
     *
     * @param  callable  $callback
     */
    public static function forEach($callback): \Illuminate\Validation\NestedRules
    {
        return new NestedRules($callback);
    }

    /**
     * Get a unique constraint builder instance.
     *
     * @param  string  $table
     * @param  string  $column
     */
    public static function unique($table, $column = 'NULL'): \Illuminate\Validation\Rules\Unique
    {
        return new Unique($table, $column);
    }

    /**
     * Get an exists constraint builder instance.
     *
     * @param  string  $table
     * @param  string  $column
     */
    public static function exists($table, $column = 'NULL'): \Illuminate\Validation\Rules\Exists
    {
        return new Exists($table, $column);
    }

    /**
     * Get an in rule builder instance.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\UnitEnum|array|string  $values
     */
    public static function in($values): \Illuminate\Validation\Rules\In
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new In(is_array($values) ? $values : func_get_args());
    }

    /**
     * Get a not_in rule builder instance.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\UnitEnum|array|string  $values
     */
    public static function notIn($values): \Illuminate\Validation\Rules\NotIn
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new NotIn(is_array($values) ? $values : func_get_args());
    }

    /**
     * Get a required_if rule builder instance.
     *
     * @param  (\Closure(): bool)|bool  $callback
     */
    public static function requiredIf($callback): \Illuminate\Validation\Rules\RequiredIf
    {
        return new RequiredIf($callback);
    }

    /**
     * Get a exclude_if rule builder instance.
     *
     * @param  (\Closure(): bool)|bool  $callback
     */
    public static function excludeIf($callback): \Illuminate\Validation\Rules\ExcludeIf
    {
        return new ExcludeIf($callback);
    }

    /**
     * Get a prohibited_if rule builder instance.
     *
     * @param  (\Closure(): bool)|bool  $callback
     */
    public static function prohibitedIf($callback): \Illuminate\Validation\Rules\ProhibitedIf
    {
        return new ProhibitedIf($callback);
    }

    /**
     * Get a date rule builder instance.
     */
    public static function date(): \Illuminate\Validation\Rules\Date
    {
        return new Date();
    }

    /**
     * Get a datetime rule builder instance.
     */
    public static function dateTime(): Date
    {
        return (new Date())->format('Y-m-d H:i:s');
    }

    /**
     * Get an email rule builder instance.
     */
    public static function email(): \Illuminate\Validation\Rules\Email
    {
        return new Email();
    }

    /**
     * Get an enum rule builder instance.
     *
     * @param  class-string  $type
     */
    public static function enum($type): \Illuminate\Validation\Rules\Enum
    {
        return new Enum($type);
    }

    /**
     * Get a file rule builder instance.
     */
    public static function file(): \Illuminate\Validation\Rules\File
    {
        return new File();
    }

    /**
     * Get an image file rule builder instance.
     *
     * @param  bool  $allowSvg
     */
    public static function imageFile($allowSvg = false): \Illuminate\Validation\Rules\ImageFile
    {
        return new ImageFile($allowSvg);
    }

    /**
     * Get a dimensions rule builder instance.
     */
    public static function dimensions(array $constraints = []): \Illuminate\Validation\Rules\Dimensions
    {
        return new Dimensions($constraints);
    }

    /**
     * Get a numeric rule builder instance.
     */
    public static function numeric(): \Illuminate\Validation\Rules\Numeric
    {
        return new Numeric();
    }

    /**
     * Get an "any of" rule builder instance.
     *
     * @param  array  $rules
     *
     * @throws \InvalidArgumentException
     */
    public static function anyOf($rules): \Illuminate\Validation\Rules\AnyOf
    {
        return new AnyOf($rules);
    }

    /**
     * Get a contains rule builder instance.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\UnitEnum|array|string  $values
     */
    public static function contains($values): \Illuminate\Validation\Rules\Contains
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new Rules\Contains(is_array($values) ? $values : func_get_args());
    }

    /**
     * Get a "does not contain" rule builder instance.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\UnitEnum|array|string  $values
     */
    public static function doesntContain($values): \Illuminate\Validation\Rules\DoesntContain
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new Rules\DoesntContain(is_array($values) ? $values : func_get_args());
    }

    /**
     * Compile a set of rules for an attribute.
     *
     * @param  array  $rules
     * @param  array|null  $data
     * @return object|\stdClass
     */
    public static function compile(string $attribute, $rules, $data = null)
    {
        $parser = new ValidationRuleParser(
            Arr::undot(Arr::wrap($data))
        );

        if (is_array($rules) && ! array_is_list($rules)) {
            $nested = [];

            foreach ($rules as $key => $rule) {
                $nested[$attribute.'.'.$key] = $rule;
            }

            $rules = $nested;
        } else {
            $rules = [$attribute => $rules];
        }

        return $parser->explode(ValidationRuleParser::filterConditionalRules($rules, $data));
    }
}
