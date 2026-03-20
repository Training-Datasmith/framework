<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Illuminate\Support\Reflector;
trait Manages_Attributes
{
    /**
     * The cron expression representing the event's frequency.
     *
     * @var string
     */
    public $expression = '* * * * *';
    /**
     * How often to repeat the event during a minute.
     *
     * @var int|null
     */
    public $repeat_seconds;
    /**
     * The timezone the date should be evaluated on.
     *
     * @var \DateTimeZone|string
     */
    public $timezone;
    /**
     * The user the command should run as.
     *
     * @var string|null
     */
    public $user;
    /**
     * The list of environments the command should run under.
     *
     * @var array
     */
    public $environments = [];
    /**
     * Indicates if the command should run in maintenance mode.
     *
     * @var bool
     */
    public $even_in_maintenance_mode = false;
    /**
     * Indicates if the command should not overlap itself.
     *
     * @var bool
     */
    public $without_overlapping = false;
    /**
     * Indicates if the command should only be allowed to run on one server for each cron expression.
     *
     * @var bool
     */
    public $on_one_server = false;
    /**
     * The number of minutes the mutex should be valid.
     *
     * @var int
     */
    public $expires_at = 1440;
    /**
     * Indicates if the command should run in the background.
     *
     * @var bool
     */
    public $run_in_background = false;
    /**
     * The array of filter callbacks.
     *
     * @var array
     */
    protected $filters = [];
    /**
     * The array of reject callbacks.
     *
     * @var array
     */
    protected $rejects = [];
    /**
     * The human-readable description of the event.
     *
     * @var string|null
     */
    public $description;
    /**
     * Set which user the command should run as.
     *
     * @param  string  $user
     * @return $this
     */
    public function user($user)
    {
        $this->user = $user;
        return $this;
    }
    /**
     * Limit the environments the command should run in.
     *
     * @param  mixed  $environments
     * @return $this
     */
    public function environments($environments)
    {
        $this->environments = is_array($environments) ? $environments : func_get_args();
        return $this;
    }
    /**
     * State that the command should run even in maintenance mode.
     *
     * @return $this
     */
    public function even_in_maintenance_mode()
    {
        $this->even_in_maintenance_mode = true;
        return $this;
    }
    /**
     * Do not allow the event to overlap each other.
     * The expiration time of the underlying cache lock may be specified in minutes.
     *
     * @param  int  $expiresAt
     * @return $this
     */
    public function without_overlapping($expires_at = 1440)
    {
        $this->without_overlapping = true;
        $this->expires_at = $expires_at;
        return $this->skip(fn() => $this->mutex->exists($this));
    }
    /**
     * Allow the event to only run on one server for each cron expression.
     *
     * @return $this
     */
    public function on_one_server()
    {
        $this->on_one_server = true;
        return $this;
    }
    /**
     * State that the command should run in the background.
     *
     * @return $this
     */
    public function run_in_background()
    {
        $this->run_in_background = true;
        return $this;
    }
    /**
     * Register a callback to further filter the schedule.
     *
     * @param  \Closure|bool  $callback
     * @return $this
     */
    public function when($callback)
    {
        $this->filters[] = Reflector::is_callable($callback) ? $callback : fn() => $callback;
        return $this;
    }
    /**
     * Register a callback to further filter the schedule.
     *
     * @param  \Closure|bool  $callback
     * @return $this
     */
    public function skip($callback)
    {
        $this->rejects[] = Reflector::is_callable($callback) ? $callback : fn() => $callback;
        return $this;
    }
    /**
     * Set the human-friendly description of the event.
     *
     * @param  string  $description
     * @return $this
     */
    public function name($description)
    {
        return $this->description($description);
    }
    /**
     * Set the human-friendly description of the event.
     *
     * @param  string  $description
     * @return $this
     */
    public function description($description)
    {
        $this->description = $description;
        return $this;
    }
}