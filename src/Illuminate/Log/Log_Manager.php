<?php

declare (strict_types=1);
namespace Illuminate\Log;

use Closure;
use Illuminate\Contracts\Log\Context_Log_Processor;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Monolog\Formatter\Line_Formatter;
use Monolog\Handler\Error_Log_Handler;
use Monolog\Handler\Fingers_Crossed_Handler;
use Monolog\Handler\Formattable_Handler_Interface;
use Monolog\Handler\Handler_Interface;
use Monolog\Handler\Rotating_File_Handler;
use Monolog\Handler\Slack_Webhook_Handler;
use Monolog\Handler\Stream_Handler;
use Monolog\Handler\Syslog_Handler;
use Monolog\Handler\What_Failure_Group_Handler;
use Monolog\Logger as Monolog;
use Monolog\Processor\Processor_Interface;
use Monolog\Processor\Psr_Log_Message_Processor;
use Psr\Log\Logger_Interface;
use Throwable;
/**
 * @mixin \Illuminate\Log\Logger
 */
class Log_Manager implements Logger_Interface
{
    use Parses_Log_Configuration;
    /**
     * The array of resolved channels.
     *
     * @var array
     */
    protected $channels = [];
    /**
     * The context shared across channels and stacks.
     *
     * @var array
     */
    protected $shared_context = [];
    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $custom_creators = [];
    /**
     * The standard date format to use when writing logs.
     *
     * @var string
     */
    protected $date_format = 'Y-m-d H:i:s';
    /**
     * Create a new Log manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function __construct(
        /**
         * The application instance.
         */
        protected $app
    )
    {
    }
    /**
     * Build an on-demand log channel.
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function build(array $config)
    {
        unset($this->channels['ondemand']);
        return $this->get('ondemand', $config);
    }
    /**
     * Create a new, on-demand aggregate logger instance.
     *
     * @param  string|null  $channel
     * @return \Psr\Log\LoggerInterface
     */
    public function stack(array $channels, $channel = null): \Illuminate\Log\Logger
    {
        return (new Logger($this->create_stack_driver(compact('channels', 'channel')), $this->app['events']))->with_context($this->shared_context);
    }
    /**
     * Get a log channel instance.
     *
     * @param  string|null  $channel
     * @return \Psr\Log\LoggerInterface
     */
    public function channel($channel = null)
    {
        return $this->driver($channel);
    }
    /**
     * Get a log driver instance.
     *
     * @param  string|null  $driver
     * @return \Psr\Log\LoggerInterface
     */
    public function driver($driver = null)
    {
        return $this->get($this->parse_driver($driver));
    }
    /**
     * Attempt to get the log from the local cache.
     *
     * @param  string  $name
     * @return \Psr\Log\LoggerInterface
     */
    protected function get($name, ?array $config = null)
    {
        try {
            return $this->channels[$name] ?? with($this->resolve($name, $config), function ($logger) use ($name): \Illuminate\Log\Logger {
                $logger_with_context = $this->tap($name, new Logger($logger, $this->app['events']))->with_context($this->shared_context);
                if (method_exists($logger_with_context->get_logger(), 'pushProcessor')) {
                    $logger_with_context->push_processor($this->app->make(Context_Log_Processor::class));
                }
                return $this->channels[$name] = $logger_with_context;
            });
        } catch (Throwable $e) {
            return tap($this->create_emergency_logger(), function ($logger) use ($e): void {
                $logger->emergency('Unable to create configured logger. Using emergency logger.', ['exception' => $e]);
            });
        }
    }
    /**
     * Apply the configured taps for the logger.
     *
     * @param  string  $name
     */
    protected function tap($name, Logger $logger): Logger
    {
        foreach ($this->configuration_for($name)['tap'] ?? [] as $tap) {
            [$class, $arguments] = $this->parse_tap($tap);
            $this->app->make($class)->__invoke($logger, ...explode(',', $arguments));
        }
        return $logger;
    }
    /**
     * Parse the given tap class string into a class name and arguments string.
     *
     * @param  string  $tap
     * @return array
     */
    protected function parse_tap($tap)
    {
        return str_contains($tap, ':') ? explode(':', $tap, 2) : [$tap, ''];
    }
    /**
     * Create an emergency log handler to avoid white screens of death.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function create_emergency_logger(): \Illuminate\Log\Logger
    {
        $config = $this->configuration_for('emergency');
        $handler = new Stream_Handler($config['path'] ?? $this->app->storage_path() . '/logs/laravel.log', $this->level(['level' => 'debug']));
        return new Logger(new Monolog('laravel', $this->prepare_handlers([$handler])), $this->app['events']);
    }
    /**
     * Resolve the given log instance by name.
     *
     * @param  string  $name
     * @return \Psr\Log\LoggerInterface
     * @throws \InvalidArgumentException
     */
    protected function resolve($name, ?array $config = null)
    {
        $config ??= $this->configuration_for($name);
        if (is_null($config)) {
            throw new InvalidArgumentException("Log [{$name}] is not defined.");
        }
        if (isset($this->custom_creators[$config['driver']])) {
            return $this->call_custom_creator($config);
        }
        $driver_method = 'create' . ucfirst((string) $config['driver']) . 'Driver';
        if (method_exists($this, $driver_method)) {
            return $this->{$driver_method}($config);
        }
        throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }
    /**
     * Call a custom driver creator.
     *
     * @return mixed
     */
    protected function call_custom_creator(array $config)
    {
        return $this->custom_creators[$config['driver']]($this->app, $config);
    }
    /**
     * Create a custom log driver instance.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function create_custom_driver(array $config)
    {
        $factory = is_callable($via = $config['via']) ? $via : $this->app->make($via);
        return $factory($config);
    }
    /**
     * Create an aggregate log driver instance.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function create_stack_driver(array $config)
    {
        if (is_string($config['channels'])) {
            $config['channels'] = explode(',', $config['channels']);
        }
        $handlers = (new Collection($config['channels']))->flat_map(fn($channel) => $channel instanceof Logger_Interface ? $channel->get_handlers() : $this->channel($channel)->get_handlers())->all();
        $processors = (new Collection($config['channels']))->flat_map(fn($channel) => $channel instanceof Logger_Interface ? $channel->get_processors() : $this->channel($channel)->get_processors())->all();
        if ($config['ignore_exceptions'] ?? false) {
            $handlers = [new What_Failure_Group_Handler($handlers)];
        }
        return new Monolog($this->parse_channel($config), $handlers, $processors);
    }
    /**
     * Create an instance of the single file log driver.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function create_single_driver(array $config)
    {
        return new Monolog($this->parse_channel($config), [$this->prepare_handler(new Stream_Handler($config['path'], $this->level($config), $config['bubble'] ?? true, $config['permission'] ?? null, $config['locking'] ?? false), $config)], $config['replace_placeholders'] ?? false ? [new Psr_Log_Message_Processor()] : []);
    }
    /**
     * Create an instance of the daily file log driver.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function create_daily_driver(array $config)
    {
        return new Monolog($this->parse_channel($config), [$this->prepare_handler(new Rotating_File_Handler($config['path'], $config['days'] ?? 7, $this->level($config), $config['bubble'] ?? true, $config['permission'] ?? null, $config['locking'] ?? false), $config)], $config['replace_placeholders'] ?? false ? [new Psr_Log_Message_Processor()] : []);
    }
    /**
     * Create an instance of the Slack log driver.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function create_slack_driver(array $config)
    {
        return new Monolog($this->parse_channel($config), [$this->prepare_handler(new Slack_Webhook_Handler($config['url'], $config['channel'] ?? null, $config['username'] ?? 'Laravel', $config['attachment'] ?? true, $config['emoji'] ?? ':boom:', $config['short'] ?? false, $config['context'] ?? true, $this->level($config), $config['bubble'] ?? true, $config['exclude_fields'] ?? []), $config)], $config['replace_placeholders'] ?? false ? [new Psr_Log_Message_Processor()] : []);
    }
    /**
     * Create an instance of the syslog log driver.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function create_syslog_driver(array $config)
    {
        return new Monolog($this->parse_channel($config), [$this->prepare_handler(new Syslog_Handler(Str::snake($this->app['config']['app.name'], '-'), $config['facility'] ?? LOG_USER, $this->level($config)), $config)], $config['replace_placeholders'] ?? false ? [new Psr_Log_Message_Processor()] : []);
    }
    /**
     * Create an instance of the "error log" log driver.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function create_errorlog_driver(array $config)
    {
        return new Monolog($this->parse_channel($config), [$this->prepare_handler(new Error_Log_Handler($config['type'] ?? Error_Log_Handler::OPERATING_SYSTEM, $this->level($config)))], $config['replace_placeholders'] ?? false ? [new Psr_Log_Message_Processor()] : []);
    }
    /**
     * Create an instance of any handler available in Monolog.
     *
     * @return \Psr\Log\LoggerInterface
     *
     * @throws \InvalidArgumentException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function create_monolog_driver(array $config)
    {
        if (!is_a($config['handler'], Handler_Interface::class, true)) {
            throw new InvalidArgumentException($config['handler'] . ' must be an instance of ' . Handler_Interface::class);
        }
        (new Collection($config['processors'] ?? []))->each(function (array $processor): void {
            $processor = $processor['processor'] ?? $processor;
            if (!is_a($processor, Processor_Interface::class, true)) {
                throw new InvalidArgumentException($processor . ' must be an instance of ' . Processor_Interface::class);
            }
        });
        $with = array_merge(['level' => $this->level($config)], $config['with'] ?? [], $config['handler_with'] ?? []);
        $handler = $this->prepare_handler($this->app->make($config['handler'], $with), $config);
        $processors = (new Collection($config['processors'] ?? []))->map(fn($processor) => $this->app->make($processor['processor'] ?? $processor, $processor['with'] ?? []))->to_array();
        return new Monolog($this->parse_channel($config), [$handler], $processors);
    }
    /**
     * Prepare the handlers for usage by Monolog.
     */
    protected function prepare_handlers(array $handlers): array
    {
        foreach ($handlers as $key => $handler) {
            $handlers[$key] = $this->prepare_handler($handler);
        }
        return $handlers;
    }
    /**
     * Prepare the handler for usage by Monolog.
     *
     * @param  \Monolog\Handler\HandlerInterface  $handler
     * @return \Monolog\Handler\HandlerInterface
     */
    protected function prepare_handler(Handler_Interface $handler, array $config = []): \Monolog\Handler\Fingers_Crossed_Handler|\Monolog\Handler\Handler_Interface|(\Monolog\Handler\Fingers_Crossed_Handler&\Monolog\Handler\Formattable_Handler_Interface)|(\Monolog\Handler\Formattable_Handler_Interface&\Monolog\Handler\Handler_Interface)
    {
        if (isset($config['action_level'])) {
            $handler = new Fingers_Crossed_Handler($handler, $this->action_level($config), 0, true, $config['stop_buffering'] ?? true);
        }
        if (!$handler instanceof Formattable_Handler_Interface) {
            return $handler;
        }
        if (!isset($config['formatter'])) {
            $handler->set_formatter($this->formatter());
        } elseif ($config['formatter'] !== 'default') {
            $handler->set_formatter($this->app->make($config['formatter'], $config['formatter_with'] ?? []));
        }
        return $handler;
    }
    /**
     * Get a Monolog formatter instance.
     *
     * @return \Monolog\Formatter\FormatterInterface
     */
    protected function formatter()
    {
        return new Line_Formatter(null, $this->date_format, true, true, true);
    }
    /**
     * Share context across channels and stacks.
     *
     * @return $this
     */
    public function share_context(array $context): static
    {
        foreach ($this->channels as $channel) {
            $channel->with_context($context);
        }
        $this->shared_context = array_merge($this->shared_context, $context);
        return $this;
    }
    /**
     * The context shared across channels and stacks.
     *
     * @return array
     */
    public function shared_context()
    {
        return $this->shared_context;
    }
    /**
     * Flush the log context on all currently resolved channels.
     *
     * @param  string[]|null  $keys
     * @return $this
     */
    public function without_context(?array $keys = null): static
    {
        foreach ($this->channels as $channel) {
            if (method_exists($channel, 'withoutContext')) {
                $channel->without_context($keys);
            }
        }
        return $this;
    }
    /**
     * Flush the shared context.
     *
     * @return $this
     */
    public function flush_shared_context(): static
    {
        $this->shared_context = [];
        return $this;
    }
    /**
     * Get fallback log channel name.
     *
     * @return string
     */
    protected function get_fallback_channel_name()
    {
        return $this->app->bound('env') ? $this->app->environment() : 'production';
    }
    /**
     * Get the log connection configuration.
     *
     * @param  string  $name
     * @return array|null
     */
    protected function configuration_for($name)
    {
        return $this->app['config']["logging.channels.{$name}"];
    }
    /**
     * Get the default log driver name.
     *
     * @return string|null
     */
    public function get_default_driver()
    {
        return $this->app['config']['logging.default'];
    }
    /**
     * Set the default log driver name.
     *
     * @param  string  $name
     */
    public function set_default_driver($name): void
    {
        $this->app['config']['logging.default'] = $name;
    }
    /**
     * Register a custom driver creator Closure.
     *
     * @param  string  $driver
     *
     * @param-closure-this  $this  $callback
     * @return $this
     */
    public function extend($driver, Closure $callback): static
    {
        $this->custom_creators[$driver] = $callback->bind_to($this, $this);
        return $this;
    }
    /**
     * Unset the given channel instance.
     *
     * @param  string|null  $driver
     */
    public function forget_channel($driver = null): void
    {
        $driver = $this->parse_driver($driver);
        if (isset($this->channels[$driver])) {
            unset($this->channels[$driver]);
        }
    }
    /**
     * Parse the driver name.
     *
     * @param  string|null  $driver
     */
    protected function parse_driver($driver): ?string
    {
        $driver ??= $this->get_default_driver();
        if ($this->app->running_unit_tests()) {
            $driver ??= 'null';
        }
        if ($driver === null) {
            return null;
        }
        return trim($driver);
    }
    /**
     * Get all of the resolved log channels.
     *
     * @return array
     */
    public function get_channels()
    {
        return $this->channels;
    }
    /**
     * System is unusable.
     *
     * @param  string|\Stringable  $message
     */
    public function emergency($message, array $context = []): void
    {
        $this->driver()->emergency($message, $context);
    }
    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param  string|\Stringable  $message
     */
    public function alert($message, array $context = []): void
    {
        $this->driver()->alert($message, $context);
    }
    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param  string|\Stringable  $message
     */
    public function critical($message, array $context = []): void
    {
        $this->driver()->critical($message, $context);
    }
    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param  string|\Stringable  $message
     */
    public function error($message, array $context = []): void
    {
        $this->driver()->error($message, $context);
    }
    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param  string|\Stringable  $message
     */
    public function warning($message, array $context = []): void
    {
        $this->driver()->warning($message, $context);
    }
    /**
     * Normal but significant events.
     *
     * @param  string|\Stringable  $message
     */
    public function notice($message, array $context = []): void
    {
        $this->driver()->notice($message, $context);
    }
    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param  string|\Stringable  $message
     */
    public function info($message, array $context = []): void
    {
        $this->driver()->info($message, $context);
    }
    /**
     * Detailed debug information.
     *
     * @param  string|\Stringable  $message
     */
    public function debug($message, array $context = []): void
    {
        $this->driver()->debug($message, $context);
    }
    /**
     * Logs with an arbitrary level.
     *
     * @param  mixed  $level
     * @param  string|\Stringable  $message
     */
    public function log($level, $message, array $context = []): void
    {
        $this->driver()->log($level, $message, $context);
    }
    /**
     * Set the application instance used by the manager.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return $this
     */
    public function set_application($app): static
    {
        $this->app = $app;
        return $this;
    }
    /**
     * Dynamically call the default driver instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->{$method}(...$parameters);
    }
}