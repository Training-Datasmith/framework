<?php

declare(strict_types=1);

namespace Illuminate\Validation\Rules;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Arr;

use function Illuminate\Support\enum_value;

use Illuminate\Support\Traits\Conditionable;
use Stringable;

use TypeError;

class Enum implements Rule, ValidatorAwareRule, Stringable
{
    use Conditionable;

    /**
     * The current validator instance.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected $validator;

    /**
     * The cases that should be considered valid.
     *
     * @var array
     */
    protected $only = [];

    /**
     * The cases that should be considered invalid.
     *
     * @var array
     */
    protected $except = [];

    /**
     * Create a new rule instance.
     *
     * @param  class-string<\UnitEnum>  $type
     */
    public function __construct(
        /**
         * The type of the enum.
         */
        protected $type
    ) {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        if ($value instanceof $this->type) {
            return $this->isDesirable($value);
        }

        if (is_null($value) || ! enum_exists($this->type) || ! method_exists($this->type, 'tryFrom')) {
            return false;
        }

        try {
            $value = $this->type::tryFrom($value);

            return ! is_null($value) && $this->isDesirable($value);
        } catch (TypeError) {
            return false;
        }
    }

    /**
     * Specify the cases that should be considered valid.
     *
     * @param  \UnitEnum[]|\UnitEnum|\Illuminate\Contracts\Support\Arrayable<array-key, \UnitEnum>  $values
     * @return $this
     */
    public function only($values): static
    {
        $this->only = $values instanceof Arrayable ? $values->toArray() : Arr::wrap($values);

        return $this;
    }

    /**
     * Specify the cases that should be considered invalid.
     *
     * @param  \UnitEnum[]|\UnitEnum|\Illuminate\Contracts\Support\Arrayable<array-key, \UnitEnum>  $values
     * @return $this
     */
    public function except($values): static
    {
        $this->except = $values instanceof Arrayable ? $values->toArray() : Arr::wrap($values);

        return $this;
    }

    /**
     * Determine if the given case is a valid case based on the only / except values.
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function isDesirable($value)
    {
        return match (true) {
            ! empty($this->only) => in_array(needle: $value, haystack: $this->only, strict: true),
            ! empty($this->except) => ! in_array(needle: $value, haystack: $this->except, strict: true),
            default => true,
        };
    }

    /**
     * Get the validation error message.
     *
     * @return array
     */
    public function message()
    {
        $message = $this->validator->getTranslator()->get('validation.enum');

        return $message === 'validation.enum'
            ? ['The selected :attribute is invalid.']
            : $message;
    }

    /**
     * Set the current validator.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return $this
     */
    public function setValidator($validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Convert the rule to a validation string.
     */
    public function __toString(): string
    {
        $cases = ! empty($this->only)
            ? $this->only
            : array_filter($this->type::cases(), fn (\UnitEnum $case): bool => ! in_array($case, $this->except, true));

        $values = array_map(function ($case): string {
            $value = enum_value($case);

            return '"'.str_replace('"', '""', (string) $value).'"';
        }, $cases);

        return 'in:'.implode(',', $values);
    }
}
