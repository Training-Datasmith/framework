<?php

namespace Illuminate\Cache\RateLimiting;

class Limit
{
    /**
     * The maximum number of attempts allowed within the given number of seconds.
     *
     * @var int
     */
    public $maxAttempts;

    /**
     * The number of seconds until the rate limit is reset.
     *
     * @var int
     */
    public $decaySeconds;

    /**
     * The after callback used to determine if the limiter should be hit.
     *
     * @var ?callable
     */
    public $afterCallback;

    /**
     * The response generator callback.
     *
     * @var callable
     */
    public $responseCallback;

    /**
     * Create a new limit instance.
     *
     * @param  mixed  $key
     */
    public function __construct(/**
     * The rate limit signature key.
     */
    public $key = '', int $maxAttempts = 60, int $decaySeconds = 60)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
    }

    /**
     * Create a new rate limit.
     *
     * @param  int  $maxAttempts
     * @param  int  $decaySeconds
     */
    public static function perSecond($maxAttempts, $decaySeconds = 1): static
    {
        return new static('', $maxAttempts, $decaySeconds);
    }

    /**
     * Create a new rate limit.
     *
     * @param  int  $maxAttempts
     * @param  int  $decayMinutes
     */
    public static function perMinute($maxAttempts, $decayMinutes = 1): static
    {
        return new static('', $maxAttempts, 60 * $decayMinutes);
    }

    /**
     * Create a new rate limit using minutes as decay time.
     *
     * @param  int  $decayMinutes
     * @param  int  $maxAttempts
     */
    public static function perMinutes($decayMinutes, $maxAttempts): static
    {
        return new static('', $maxAttempts, 60 * $decayMinutes);
    }

    /**
     * Create a new rate limit using hours as decay time.
     *
     * @param  int  $maxAttempts
     * @param  int  $decayHours
     */
    public static function perHour($maxAttempts, $decayHours = 1): static
    {
        return new static('', $maxAttempts, 60 * 60 * $decayHours);
    }

    /**
     * Create a new rate limit using days as decay time.
     *
     * @param  int  $maxAttempts
     * @param  int  $decayDays
     */
    public static function perDay($maxAttempts, $decayDays = 1): static
    {
        return new static('', $maxAttempts, 60 * 60 * 24 * $decayDays);
    }

    /**
     * Create a new unlimited rate limit.
     *
     * @return static
     */
    public static function none(): \Illuminate\Cache\RateLimiting\Unlimited
    {
        return new Unlimited;
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
        $this->afterCallback = $callback;

        return $this;
    }

    /**
     * Set the callback that should generate the response when the limit is exceeded.
     *
     * @return $this
     */
    public function response(callable $callback): static
    {
        $this->responseCallback = $callback;

        return $this;
    }

    /**
     * Get a potential fallback key for the limit.
     */
    public function fallbackKey(): string
    {
        $prefix = $this->key ? "{$this->key}:" : '';

        return "{$prefix}attempts:{$this->maxAttempts}:decay:{$this->decaySeconds}";
    }
}
