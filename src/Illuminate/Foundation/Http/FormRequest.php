<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http;

use Illuminate\Auth\Access\Authorization_Exception;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\Validates_When_Resolved;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\Validates_When_Resolved_Trait;
class Form_Request extends Request implements Validates_When_Resolved
{
    use Validates_When_Resolved_Trait;
    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;
    /**
     * The redirector instance.
     *
     * @var \Illuminate\Routing\Redirector
     */
    protected $redirector;
    /**
     * The URI to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirect;
    /**
     * The route to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirect_route;
    /**
     * The controller action to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirect_action;
    /**
     * The key to be used for the view error bag.
     *
     * @var string
     */
    protected $error_bag = 'default';
    /**
     * Indicates whether validation should stop after the first rule failure.
     *
     * @var bool
     */
    protected $stop_on_first_failure = false;
    /**
     * The validator instance.
     *
     * @var \Illuminate\Contracts\Validation\Validator
     */
    protected $validator;
    /**
     * Get the validator instance for the request.
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function get_validator_instance()
    {
        if ($this->validator) {
            return $this->validator;
        }
        $factory = $this->container->make(Validation_Factory::class);
        if (method_exists($this, 'validator')) {
            $validator = $this->container->call($this->validator(...), compact('factory'));
        } else {
            $validator = $this->create_default_validator($factory);
        }
        if (method_exists($this, 'withValidator')) {
            $this->with_validator($validator);
        }
        if (method_exists($this, 'after')) {
            $validator->after($this->container->call($this->after(...), ['validator' => $validator]));
        }
        $this->set_validator($validator);
        return $this->validator;
    }
    /**
     * Create the default validator instance.
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function create_default_validator(Validation_Factory $factory)
    {
        $rules = $this->validation_rules();
        $validator = $factory->make($this->validation_data(), $rules, $this->messages(), $this->attributes())->stop_on_first_failure($this->stop_on_first_failure);
        if ($this->is_precognitive()) {
            $validator->set_rules($this->filter_precognitive_rules($validator->get_rules_without_placeholders()));
        }
        return $validator;
    }
    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    public function validation_data()
    {
        return $this->all();
    }
    /**
     * Get the validation rules for this form request.
     *
     * @return array
     */
    protected function validation_rules()
    {
        return method_exists($this, 'rules') ? $this->container->call([$this, 'rules']) : [];
    }
    /**
     * Handle a failed validation attempt.
     *
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failed_validation(Validator $validator)
    {
        $exception = $validator->get_exception();
        throw (new $exception($validator))->error_bag($this->error_bag)->redirect_to($this->get_redirect_url());
    }
    /**
     * Get the URL to redirect to on a validation error.
     *
     * @return string
     */
    protected function get_redirect_url()
    {
        $url = $this->redirector->get_url_generator();
        if ($this->redirect) {
            return $url->to($this->redirect);
        }
        if ($this->redirect_route) {
            return $url->route($this->redirect_route);
        }
        if ($this->redirect_action) {
            return $url->action($this->redirect_action);
        }
        return $url->previous();
    }
    /**
     * Determine if the request passes the authorization check.
     *
     * @return bool
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function passes_authorization()
    {
        if (method_exists($this, 'authorize')) {
            $result = $this->container->call([$this, 'authorize']);
            return $result instanceof Response ? $result->authorize() : $result;
        }
        return true;
    }
    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function failed_authorization()
    {
        throw new Authorization_Exception();
    }
    /**
     * Get a validated input container for the validated input.
     *
     * @return \Illuminate\Support\ValidatedInput|array
     */
    public function safe(?array $keys = null)
    {
        return is_array($keys) ? $this->validator->safe()->only($keys) : $this->validator->safe();
    }
    /**
     * Get the validated data from the request.
     *
     * @param  array|int|string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        return data_get($this->validator->validated(), $key, $default);
    }
    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [];
    }
    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes()
    {
        return [];
    }
    /**
     * Set the Validator instance.
     *
     * @return $this
     */
    public function set_validator(Validator $validator)
    {
        $this->validator = $validator;
        return $this;
    }
    /**
     * Set the Redirector instance.
     *
     * @return $this
     */
    public function set_redirector(Redirector $redirector)
    {
        $this->redirector = $redirector;
        return $this;
    }
    /**
     * Set the container implementation.
     *
     * @return $this
     */
    public function set_container(Container $container)
    {
        $this->container = $container;
        return $this;
    }
}