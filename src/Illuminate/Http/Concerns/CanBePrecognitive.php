<?php

declare (strict_types=1);
namespace Illuminate\Http\Concerns;

use Illuminate\Support\Collection;
trait Can_Be_Precognitive
{
    /**
     * Filter the given array of rules into an array of rules that are included in precognitive headers.
     *
     * @param  array  $rules
     * @return array
     */
    public function filter_precognitive_rules($rules)
    {
        if (!$this->headers->has('Precognition-Validate-Only')) {
            return $rules;
        }
        $validate_only = explode(',', $this->header('Precognition-Validate-Only'));
        return (new Collection($rules))->filter(fn($rule, $attribute) => $this->should_validate_precognitive_attribute($attribute, $validate_only))->all();
    }
    /**
     * Determine if the given attribute should be validated.
     *
     * @param  string  $attribute
     * @param  array  $validateOnly
     */
    protected function should_validate_precognitive_attribute($attribute, $validate_only): bool
    {
        foreach ($validate_only as $pattern) {
            $regex = '/^' . str_replace('\*', '[^.]+', preg_quote((string) $pattern, '/')) . '$/';
            if (preg_match($regex, $attribute)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Determine if the request is attempting to be precognitive.
     */
    public function is_attempting_precognition(): bool
    {
        return $this->header('Precognition') === 'true';
    }
    /**
     * Determine if the request is precognitive.
     *
     * @return bool
     */
    public function is_precognitive()
    {
        return $this->attributes->get('precognitive', false);
    }
}