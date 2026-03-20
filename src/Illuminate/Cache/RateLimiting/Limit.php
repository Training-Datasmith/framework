<?php

declare (strict_types=1);
namespace Illuminate\Cache\Rate_Limiting;

class Limit
{
    /**
     * The maximum number of attempts allowed within the given number of seconds.
     *
     * @var int
     */
    public $max_attempts;
    /**
     * The number of seconds until the rate limit is reset.
     *
     * @var int
     */
    public $decay_seconds;
    /**
     * The after callback used to determine if the limiter should be hit.
     *
     * @var ?callable
     */
    public $after_callback;
    /**
     * The response generator callback.
     *
     * @var callable
     */
    public $response_callback;
    /**
     * Create a new limit instance.
     *
     * @param  mixed  $key
     */
    public function __construct(
        /**
         * The rate limit signature key.
         */
        public $key = '',
        int $max_attempts = 60,
        int $decay_seconds = 60
    )
    {
        $this->max_attempts = $max_attempts;
        $this->decay_seconds = $decay_seconds;
    }
    /**
     * Create a new rate limit.
     *
     * @param  int  $maxAttempts
     * @param  int  $decaySeconds
     */
    public static function per_second($max_attempts, $decay_seconds = 1): static
    {
        return new static('', $max_attempts, $decay_seconds);
    }
    /**
     * Create a new rate limit.
     *
     * @param  int  $maxAttempts
     * @param  int  $decayMinutes
     */
    public static function per_minute($max_attempts, $decay_minutes = 1): static
    {
        return new static('', $max_attempts, 60 * $decay_minutes);
    }
    /**
     * Create a new rate limit using minutes as decay time.
     *
     * @param  int  $decayMinutes
     * @param  int  $maxAttempts
     */
    public static function per_minutes($decay_minutes, $max_attempts): static
    {
        return new static('', $max_attempts, 60 * $decay_minutes);
    }
    /**
     * Create a new rate limit using hours as decay time.
     *
     * @param  int  $maxAttempts
     * @param  int  $decayHours
     */
    public static function per_hour($max_attempts, $decay_hours = 1): static
    {
        return new static('', $max_attempts, 60 * 60 * $decay_hours);
    }
    /**
     * Create a new rate limit using days as decay time.
     *
     * @param  int  $maxAttempts
     * @param  int  $decayDays
     */
    public static function per_day($max_attempts, $decay_days = 1): static
    {
        return new static('', $max_attempts, 60 * 60 * 24 * $decay_days);
    }
    /**
     * Create a new unlimited rate limit.
     *
     * @return static
     */
    public static function none(): \Illuminate\Cache\Rate_Limiting\Unlimited
    {
        return new Unlimited();
    }
    /**
     * Set the key of the rate limit.
     *
     * @param  mixed  $key
     * @return $this
     */
    public function by($key): static
    {
        $this->key = $key;
        return $this;
    }
    /**
     * Set the callback to determine if the limiter should be hit.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function after($callback): static
    {
        $this->after_callback = $callback;
        return $this;
    }
    /**
     * Set the callback that should generate the response when the limit is exceeded.
     *
     * @return $this
     */
    public function response(callable $callback): static
    {
        $this->response_callback = $callback;
        return $this;
    }
    /**
     * Get a potential fallback key for the limit.
     */
    public function fallback_key(): string
    {
        $prefix = $this->key ? "{$this->key}:" : '';
        return "{$prefix}attempts:{$this->max_attempts}:decay:{$this->decay_seconds}";
    }
}