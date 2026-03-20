<?php

declare (strict_types=1);
namespace Illuminate\Support;

class Higher_Order_When_Proxy
{
    /**
     * The condition for proxying.
     *
     * @var bool
     */
    protected $condition;
    /**
     * Indicates whether the proxy has a condition.
     *
     * @var bool
     */
    protected $has_condition = false;
    /**
     * Determine whether the condition should be negated.
     *
     * @var bool
     */
    protected $negate_condition_on_capture;
    /**
     * Create a new proxy instance.
     *
     * @param  mixed  $target
     */
    public function __construct(
        /**
         * The target being conditionally operated on.
         */
        protected $target
    )
    {
    }
    /**
     * Set the condition on the proxy.
     *
     * @param  bool  $condition
     * @return $this
     */
    public function condition($condition): static
    {
        [$this->condition, $this->has_condition] = [$condition, true];
        return $this;
    }
    /**
     * Indicate that the condition should be negated.
     *
     * @return $this
     */
    public function negate_condition_on_capture(): static
    {
        $this->negate_condition_on_capture = true;
        return $this;
    }
    /**
     * Proxy accessing an attribute onto the target.
     */
    public function __get(string $key): mixed
    {
        if (!$this->has_condition) {
            $condition = $this->target->{$key};
            return $this->condition($this->negate_condition_on_capture ? !$condition : $condition);
        }
        return $this->condition ? $this->target->{$key} : $this->target;
    }
    /**
     * Proxy a method call on the target.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (!$this->has_condition) {
            $condition = $this->target->{$method}(...$parameters);
            return $this->condition($this->negate_condition_on_capture ? !$condition : $condition);
        }
        return $this->condition ? $this->target->{$method}(...$parameters) : $this->target;
    }
}