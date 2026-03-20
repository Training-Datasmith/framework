<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Validation;

use Closure;
/**
 * @deprecated see ValidationRule
 */
interface Invokable_Rule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function __invoke(string $attribute, mixed $value, Closure $fail);
}