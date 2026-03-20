<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Validation;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Foundation\Precognition;
use Illuminate\Http\Request;
use Illuminate\Validation\Validation_Exception;
trait Validates_Requests
{
    /**
     * Run the validation routine against the given validator.
     *
     * @param  \Illuminate\Contracts\Validation\Validator|array  $validator
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate_with($validator, ?Request $request = null)
    {
        $request = $request ?: request();
        if (is_array($validator)) {
            $validator = $this->get_validation_factory()->make($request->all(), $validator);
        }
        if ($request->is_precognitive()) {
            $validator->after(Precognition::after_validation_hook($request))->set_rules($request->filter_precognitive_rules($validator->get_rules_without_placeholders()));
        }
        return $validator->validate();
    }
    /**
     * Validate the given request with the given rules.
     *
     * @return array
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(Request $request, array $rules, array $messages = [], array $attributes = [])
    {
        $validator = $this->get_validation_factory()->make($request->all(), $rules, $messages, $attributes);
        if ($request->is_precognitive()) {
            $validator->after(Precognition::after_validation_hook($request))->set_rules($request->filter_precognitive_rules($validator->get_rules_without_placeholders()));
        }
        return $validator->validate();
    }
    /**
     * Validate the given request with the given rules.
     *
     * @param  string  $errorBag
     * @return array
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate_with_bag($error_bag, Request $request, array $rules, array $messages = [], array $attributes = [])
    {
        try {
            return $this->validate($request, $rules, $messages, $attributes);
        } catch (Validation_Exception $e) {
            $e->error_bag = $error_bag;
            throw $e;
        }
    }
    /**
     * Get a validation factory instance.
     *
     * @return \Illuminate\Contracts\Validation\Factory
     */
    protected function get_validation_factory()
    {
        return app(Factory::class);
    }
}