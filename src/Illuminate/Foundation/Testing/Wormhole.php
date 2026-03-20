<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

use Illuminate\Support\Carbon;
class Wormhole
{
    /**
     * Create a new wormhole instance.
     *
     * @param  int  $value
     */
    public function __construct(
        /**
         * The amount of time to travel.
         */
        public $value
    )
    {
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of microseconds.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function microsecond($callback = null)
    {
        return $this->microseconds($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of microseconds.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function microseconds($callback = null)
    {
        Carbon::set_test_now(Carbon::now()->add_microseconds($this->value));
        return $this->handle_callback($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of milliseconds.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function millisecond($callback = null)
    {
        return $this->milliseconds($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of milliseconds.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function milliseconds($callback = null)
    {
        Carbon::set_test_now(Carbon::now()->add_milliseconds($this->value));
        return $this->handle_callback($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of seconds.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function second($callback = null)
    {
        return $this->seconds($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of seconds.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function seconds($callback = null)
    {
        Carbon::set_test_now(Carbon::now()->add_seconds($this->value));
        return $this->handle_callback($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of minutes.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function minute($callback = null)
    {
        return $this->minutes($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of minutes.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function minutes($callback = null)
    {
        Carbon::set_test_now(Carbon::now()->add_minutes($this->value));
        return $this->handle_callback($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of hours.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function hour($callback = null)
    {
        return $this->hours($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of hours.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function hours($callback = null)
    {
        Carbon::set_test_now(Carbon::now()->add_hours($this->value));
        return $this->handle_callback($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of days.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function day($callback = null)
    {
        return $this->days($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of days.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function days($callback = null)
    {
        Carbon::set_test_now(Carbon::now()->add_days($this->value));
        return $this->handle_callback($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of weeks.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function week($callback = null)
    {
        return $this->weeks($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of weeks.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function weeks($callback = null)
    {
        Carbon::set_test_now(Carbon::now()->add_weeks($this->value));
        return $this->handle_callback($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of months.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function month($callback = null)
    {
        return $this->months($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of months.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function months($callback = null)
    {
        Carbon::set_test_now(Carbon::now()->add_months($this->value));
        return $this->handle_callback($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of years.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function year($callback = null)
    {
        return $this->years($callback);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel forward the given number of years.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function years($callback = null)
    {
        Carbon::set_test_now(Carbon::now()->add_years($this->value));
        return $this->handle_callback($callback);
    }
    /**
     * Travel back to the current time.
     *
     * @return \DateTimeInterface
     */
    public static function back()
    {
        Carbon::set_test_now();
        return Carbon::now();
    }
    /**
     * @template TReturn of mixed
     *
     * Handle the given optional execution callback.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    protected function handle_callback($callback)
    {
        if ($callback) {
            return tap($callback(), function (): void {
                Carbon::set_test_now();
            });
        }
    }
}