<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Closure;
use Cron\Cron_Expression;
use Guzzle_Http\Client as HttpClient;
use Guzzle_Http\Client_Interface as HttpClientInterface;
use Guzzle_Http\Exception\Transfer_Exception;
use Illuminate\Console\Application;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\Exception_Handler;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Log\Context\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Stringable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Reflects_Closures;
use Illuminate\Support\Traits\Tappable;
use Psr\Http\Client\Client_Exception_Interface;
use Symfony\Component\Process\Process;
use Throwable;
class Event
{
    use Macroable;
    use Manages_Attributes;
    use Manages_Frequencies;
    use Reflects_Closures;
    use Tappable;
    /**
     * The location that output should be sent to.
     *
     * @var string
     */
    public $output = '/dev/null';
    /**
     * Indicates whether output should be appended.
     *
     * @var bool
     */
    public $should_append_output = false;
    /**
     * The array of callbacks to be run before the event is started.
     *
     * @var array
     */
    protected $before_callbacks = [];
    /**
     * The array of callbacks to be run after the event is finished.
     *
     * @var array
     */
    protected $after_callbacks = [];
    /**
     * The event mutex implementation.
     *
     * @var \Illuminate\Console\Scheduling\EventMutex
     */
    public $mutex;
    /**
     * The mutex name resolver callback.
     *
     * @var \Closure|null
     */
    public $mutex_name_resolver;
    /**
     * The last time the event was checked for eligibility to run.
     *
     * Utilized by sub-minute repeated events.
     *
     * @var \Illuminate\Support\Carbon|null
     */
    protected $last_checked;
    /**
     * The exit status code of the command.
     *
     * @var int|null
     */
    public $exit_code;
    /**
     * Create a new event instance.
     *
     * @param  string  $command
     * @param  \DateTimeZone|string|null  $timezone
     */
    public function __construct(
        Event_Mutex $mutex,
        /**
         * The command string.
         */
        public $command,
        $timezone = null
    )
    {
        $this->mutex = $mutex;
        $this->timezone = $timezone;
        $this->output = $this->get_default_output();
    }
    /**
     * Get the default output depending on the OS.
     */
    public function get_default_output(): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    }
    /**
     * Run the given event.
     *
     *
     * @throws \Throwable
     */
    public function run(Container $container): void
    {
        if ($this->should_skip_due_to_overlapping()) {
            return;
        }
        $exit_code = $this->start($container);
        if (!$this->run_in_background) {
            $this->finish($container, $exit_code);
        }
    }
    /**
     * Determine if the event should skip because another process is overlapping.
     */
    public function should_skip_due_to_overlapping(): bool
    {
        return $this->without_overlapping && !$this->mutex->create($this);
    }
    /**
     * Determine if the event has been configured to repeat multiple times per minute.
     */
    public function is_repeatable(): bool
    {
        return !is_null($this->repeat_seconds);
    }
    /**
     * Determine if the event is ready to repeat.
     */
    public function should_repeat_now(): bool
    {
        return $this->is_repeatable() && $this->last_checked?->diff_in_seconds() >= $this->repeat_seconds;
    }
    /**
     * Run the command process.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     *
     * @throws \Throwable
     */
    protected function start(\Illuminate\Contracts\Container\Container|array $container): int
    {
        try {
            $this->call_before_callbacks($container);
            return $this->execute($container);
        } catch (Throwable $exception) {
            $this->remove_mutex();
            throw $exception;
        }
    }
    /**
     * Run the command process.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     */
    protected function execute(array $container): int
    {
        $context = json_encode($container[Repository::class]->dehydrate());
        return Process::from_shell_commandline($this->build_command(), base_path(), ['__LARAVEL_CONTEXT' => $context], null, null)->run(laravel_cloud() ? fn($type, $line): int|false => fwrite($type === 'out' ? STDOUT : STDERR, (string) $line) : fn(): true => true);
    }
    /**
     * Mark the command process as finished and run callbacks/cleanup.
     *
     * @param  int  $exitCode
     */
    public function finish(Container $container, $exit_code): void
    {
        $this->exit_code = (int) $exit_code;
        try {
            $this->call_after_callbacks($container);
        } finally {
            $this->remove_mutex();
        }
    }
    /**
     * Call all of the "before" callbacks for the event.
     */
    public function call_before_callbacks(Container $container): void
    {
        foreach ($this->before_callbacks as $callback) {
            $container->call($callback);
        }
    }
    /**
     * Call all of the "after" callbacks for the event.
     */
    public function call_after_callbacks(Container $container): void
    {
        foreach ($this->after_callbacks as $callback) {
            $container->call($callback);
        }
    }
    /**
     * Build the command string.
     *
     * @return string
     */
    public function build_command()
    {
        return (new Command_Builder())->build_command($this);
    }
    /**
     * Determine if the given event should run based on the Cron expression.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return bool
     */
    public function is_due($app)
    {
        if (!$this->runs_in_maintenance_mode() && $app->is_down_for_maintenance()) {
            return false;
        }
        return $this->expression_passes() && $this->runs_in_environment($app->environment());
    }
    /**
     * Determine if the event runs in maintenance mode.
     *
     * @return bool
     */
    public function runs_in_maintenance_mode()
    {
        return $this->even_in_maintenance_mode;
    }
    /**
     * Determine if the Cron expression passes.
     *
     * @return bool
     */
    protected function expression_passes()
    {
        $date = Date::now();
        if ($this->timezone) {
            $date = $date->set_timezone($this->timezone);
        }
        return (new Cron_Expression($this->expression))->is_due($date->to_date_time_string());
    }
    /**
     * Determine if the event runs in the given environment.
     *
     * @param  string  $environment
     */
    public function runs_in_environment($environment): bool
    {
        return empty($this->environments) || in_array($environment, $this->environments);
    }
    /**
     * Determine if the filters pass for the event.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function filters_pass($app): bool
    {
        $this->last_checked = Date::now();
        foreach ($this->filters as $callback) {
            if (!$app->call($callback)) {
                return false;
            }
        }
        foreach ($this->rejects as $callback) {
            if ($app->call($callback)) {
                return false;
            }
        }
        return true;
    }
    /**
     * Ensure that the output is stored on disk in a log file.
     *
     * @return $this
     */
    public function store_output(): static
    {
        $this->ensure_output_is_being_captured();
        return $this;
    }
    /**
     * Send the output of the command to a given location.
     *
     * @param  string  $location
     * @param  bool  $append
     * @return $this
     */
    public function send_output_to($location, $append = false): static
    {
        $this->output = $location;
        $this->should_append_output = $append;
        return $this;
    }
    /**
     * Append the output of the command to a given location.
     *
     * @param  string  $location
     * @return $this
     */
    public function append_output_to($location): static
    {
        return $this->send_output_to($location, true);
    }
    /**
     * E-mail the results of the scheduled operation.
     *
     * @param  mixed  $addresses
     * @param  bool  $onlyIfOutputExists
     * @return $this
     *
     * @throws \LogicException
     */
    public function email_output_to($addresses, $only_if_output_exists = true)
    {
        $this->ensure_output_is_being_captured();
        $addresses = Arr::wrap($addresses);
        return $this->then(function (Mailer $mailer) use ($addresses, $only_if_output_exists): void {
            $this->email_output($mailer, $addresses, $only_if_output_exists);
        });
    }
    /**
     * E-mail the results of the scheduled operation if it produces output.
     *
     * @param  mixed  $addresses
     * @return $this
     *
     * @throws \LogicException
     */
    public function email_written_output_to($addresses)
    {
        return $this->email_output_to($addresses, true);
    }
    /**
     * E-mail the results of the scheduled operation if it fails.
     *
     * @param  mixed  $addresses
     * @return $this
     */
    public function email_output_on_failure($addresses)
    {
        $this->ensure_output_is_being_captured();
        $addresses = Arr::wrap($addresses);
        return $this->on_failure(function (Mailer $mailer) use ($addresses): void {
            $this->email_output($mailer, $addresses, false);
        });
    }
    /**
     * Ensure that the command output is being captured.
     *
     * @return void
     */
    protected function ensure_output_is_being_captured()
    {
        if (is_null($this->output) || $this->output == $this->get_default_output()) {
            $this->send_output_to(storage_path('logs/schedule-' . sha1($this->mutex_name()) . '.log'));
        }
    }
    /**
     * E-mail the output of the event to the recipients.
     *
     * @param  array  $addresses
     * @param  bool  $onlyIfOutputExists
     * @return void
     */
    protected function email_output(Mailer $mailer, $addresses, $only_if_output_exists = true)
    {
        $text = is_file($this->output) ? file_get_contents($this->output) : '';
        if ($only_if_output_exists && empty($text)) {
            return;
        }
        $mailer->raw($text, function ($m) use ($addresses): void {
            $m->to($addresses)->subject($this->get_email_subject());
        });
    }
    /**
     * Get the e-mail subject line for output results.
     *
     * @return string
     */
    protected function get_email_subject()
    {
        if ($this->description) {
            return $this->description;
        }
        return "Scheduled Job Output For [{$this->command}]";
    }
    /**
     * Register a callback to ping a given URL before the job runs.
     *
     * @param  string  $url
     * @return $this
     */
    public function ping_before($url): static
    {
        return $this->before($this->ping_callback($url));
    }
    /**
     * Register a callback to ping a given URL before the job runs if the given condition is true.
     *
     * @param  bool  $value
     * @param  string  $url
     * @return $this
     */
    public function ping_before_if($value, $url)
    {
        return $value ? $this->ping_before($url) : $this;
    }
    /**
     * Register a callback to ping a given URL after the job runs.
     *
     * @param  string  $url
     * @return $this
     */
    public function then_ping($url)
    {
        return $this->then($this->ping_callback($url));
    }
    /**
     * Register a callback to ping a given URL after the job runs if the given condition is true.
     *
     * @param  bool  $value
     * @param  string  $url
     * @return $this
     */
    public function then_ping_if($value, $url)
    {
        return $value ? $this->then_ping($url) : $this;
    }
    /**
     * Register a callback to ping a given URL if the operation succeeds.
     *
     * @param  string  $url
     * @return $this
     */
    public function ping_on_success($url)
    {
        return $this->on_success($this->ping_callback($url));
    }
    /**
     * Register a callback to ping a given URL if the operation succeeds and if the given condition is true.
     *
     * @param  bool  $value
     * @param  string  $url
     * @return $this
     */
    public function ping_on_success_if($value, $url)
    {
        return $value ? $this->on_success($this->ping_callback($url)) : $this;
    }
    /**
     * Register a callback to ping a given URL if the operation fails.
     *
     * @param  string  $url
     * @return $this
     */
    public function ping_on_failure($url)
    {
        return $this->on_failure($this->ping_callback($url));
    }
    /**
     * Register a callback to ping a given URL if the operation fails and if the given condition is true.
     *
     * @param  bool  $value
     * @param  string  $url
     * @return $this
     */
    public function ping_on_failure_if($value, $url)
    {
        return $value ? $this->on_failure($this->ping_callback($url)) : $this;
    }
    /**
     * Get the callback that pings the given URL.
     *
     * @param  string  $url
     * @return \Closure
     */
    protected function ping_callback($url)
    {
        return function (Container $container) use ($url): void {
            try {
                $this->get_http_client($container)->request('GET', $url);
            } catch (Client_Exception_Interface|Transfer_Exception $e) {
                $container->make(Exception_Handler::class)->report($e);
            }
        };
    }
    /**
     * Get the Guzzle HTTP client to use to send pings.
     *
     * @return \GuzzleHttp\ClientInterface
     */
    protected function get_http_client(Container $container)
    {
        return match (true) {
            $container->bound(Http_Client_Interface::class) => $container->make(Http_Client_Interface::class),
            $container->bound(Http_Client::class) => $container->make(Http_Client::class),
            default => new Http_Client(['connect_timeout' => 10, 'crypto_method' => Stream_crypto_method_tl_Sv1_2_client, 'timeout' => 30]),
        };
    }
    /**
     * Register a callback to be called before the operation.
     *
     * @return $this
     */
    public function before(Closure $callback): static
    {
        $this->before_callbacks[] = $callback;
        return $this;
    }
    /**
     * Register a callback to be called after the operation.
     *
     * @return $this
     */
    public function after(Closure $callback)
    {
        return $this->then($callback);
    }
    /**
     * Register a callback to be called after the operation.
     *
     * @return $this
     */
    public function then(Closure $callback)
    {
        $parameters = $this->closure_parameter_types($callback);
        if (Arr::get($parameters, 'output') === Stringable::class) {
            return $this->then_with_output($callback);
        }
        $this->after_callbacks[] = $callback;
        return $this;
    }
    /**
     * Register a callback that uses the output after the job runs.
     *
     * @param  bool  $onlyIfOutputExists
     * @return $this
     */
    public function then_with_output(Closure $callback, $only_if_output_exists = false)
    {
        $this->ensure_output_is_being_captured();
        return $this->then($this->with_output_callback($callback, $only_if_output_exists));
    }
    /**
     * Register a callback to be called if the operation succeeds.
     *
     * @return $this
     */
    public function on_success(Closure $callback)
    {
        $parameters = $this->closure_parameter_types($callback);
        if (Arr::get($parameters, 'output') === Stringable::class) {
            return $this->on_success_with_output($callback);
        }
        return $this->then(function (Container $container) use ($callback): void {
            if ($this->exit_code === 0) {
                $container->call($callback);
            }
        });
    }
    /**
     * Register a callback that uses the output if the operation succeeds.
     *
     * @param  bool  $onlyIfOutputExists
     * @return $this
     */
    public function on_success_with_output(Closure $callback, $only_if_output_exists = false)
    {
        $this->ensure_output_is_being_captured();
        return $this->on_success($this->with_output_callback($callback, $only_if_output_exists));
    }
    /**
     * Register a callback to be called if the operation fails.
     *
     * @return $this
     */
    public function on_failure(Closure $callback)
    {
        $parameters = $this->closure_parameter_types($callback);
        if (Arr::get($parameters, 'output') === Stringable::class) {
            return $this->on_failure_with_output($callback);
        }
        return $this->then(function (Container $container) use ($callback): void {
            if ($this->exit_code !== 0) {
                $container->call($callback);
            }
        });
    }
    /**
     * Register a callback that uses the output if the operation fails.
     *
     * @param  bool  $onlyIfOutputExists
     * @return $this
     */
    public function on_failure_with_output(Closure $callback, $only_if_output_exists = false)
    {
        $this->ensure_output_is_being_captured();
        return $this->on_failure($this->with_output_callback($callback, $only_if_output_exists));
    }
    /**
     * Get a callback that provides output.
     *
     * @param  bool  $onlyIfOutputExists
     * @return \Closure
     */
    protected function with_output_callback(Closure $callback, $only_if_output_exists = false)
    {
        return function (Container $container) use ($callback, $only_if_output_exists) {
            $output = $this->output && is_file($this->output) ? file_get_contents($this->output) : '';
            return $only_if_output_exists && empty($output) ? null : $container->call($callback, ['output' => new Stringable($output)]);
        };
    }
    /**
     * Get the summary of the event for display.
     *
     * @return string
     */
    public function get_summary_for_display()
    {
        if (is_string($this->description)) {
            return $this->description;
        }
        return $this->build_command();
    }
    /**
     * Determine the next due date for an event.
     *
     * @param  \DateTimeInterface|string  $currentTime
     * @param  int  $nth
     * @param  bool  $allowCurrentDate
     * @return \Illuminate\Support\Carbon
     */
    public function next_run_date($current_time = 'now', $nth = 0, $allow_current_date = false)
    {
        return Date::instance((new Cron_Expression($this->get_expression()))->get_next_run_date($current_time, $nth, $allow_current_date, $this->timezone));
    }
    /**
     * Get the Cron expression for the event.
     *
     * @return string
     */
    public function get_expression()
    {
        return $this->expression;
    }
    /**
     * Set the event mutex implementation to be used.
     *
     * @return $this
     */
    public function prevent_overlaps_using(Event_Mutex $mutex): static
    {
        $this->mutex = $mutex;
        return $this;
    }
    /**
     * Get the mutex name for the scheduled command.
     *
     * @return string
     */
    public function mutex_name()
    {
        $mutex_name_resolver = $this->mutex_name_resolver;
        if (!is_null($mutex_name_resolver) && is_callable($mutex_name_resolver)) {
            return $mutex_name_resolver($this);
        }
        return 'framework' . DIRECTORY_SEPARATOR . 'schedule-' . sha1($this->expression . static::normalize_command($this->command ?? ''));
    }
    /**
     * Set the mutex name or name resolver callback.
     *
     * @return $this
     */
    public function create_mutex_name_using(Closure|string $mutex_name): static
    {
        $this->mutex_name_resolver = is_string($mutex_name) ? fn(): string => $mutex_name : $mutex_name;
        return $this;
    }
    /**
     * Delete the mutex for the event.
     *
     * @return void
     */
    protected function remove_mutex()
    {
        if ($this->without_overlapping) {
            $this->mutex->forget($this);
        }
    }
    /**
     * Format the given command string with a normalized PHP binary path.
     *
     * @param  string  $command
     */
    public static function normalize_command($command): string
    {
        return str_replace([Application::php_binary(), Application::artisan_binary()], ['php', preg_replace("#['\"]#", '', Application::artisan_binary())], $command);
    }
}