<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

use Illuminate\Foundation\Testing\Wormhole;
use Illuminate\Support\Carbon;
trait Interacts_With_Time
{
    /**
     * @template TReturn of mixed
     *
     * Freeze time.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? \Illuminate\Support\Carbon : TReturn)
     */
    public function freeze_time($callback = null)
    {
        $result = $this->travel_to($now = Carbon::now(), $callback);
        return is_null($callback) ? $now : $result;
    }
    /**
     * @template TReturn of mixed
     *
     * Freeze time at the beginning of the current second.
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? \Illuminate\Support\Carbon : TReturn)
     */
    public function freeze_second($callback = null)
    {
        $result = $this->travel_to($now = Carbon::now()->start_of_second(), $callback);
        return is_null($callback) ? $now : $result;
    }
    /**
     * Begin travelling to another time.
     *
     * @param  int  $value
     */
    public function travel($value): \Illuminate\Foundation\Testing\Wormhole
    {
        return new Wormhole($value);
    }
    /**
     * @template TReturn of mixed
     *
     * Travel to another time.
     *
     * @param  \DateTimeInterface|\Closure|\Illuminate\Support\Carbon|string|bool|null  $date
     * @param  (callable(): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function travel_to($date, $callback = null)
    {
        Carbon::set_test_now($date);
        if ($callback) {
            return tap($callback($date), function (): void {
                Carbon::set_test_now();
            });
        }
    }
    /**
     * Travel back to the current time.
     *
     * @return \DateTimeInterface
     */
    public function travel_back()
    {
        return Wormhole::back();
    }
}