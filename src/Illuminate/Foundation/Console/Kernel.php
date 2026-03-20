<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Carbon\Carbon_Interval;
use Closure;
use DateTimeInterface;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command;
use Illuminate\Console\Events\Command_Finished;
use Illuminate\Console\Events\Command_Starting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Contracts\Debug\Exception_Handler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Illuminate\Support\Interacts_With_Time;
use Illuminate\Support\Str;
use ReflectionClass;
use Spl_File_Info;
use Symfony\Component\Console\Console_Events;
use Symfony\Component\Console\Event\Console_Command_Event;
use Symfony\Component\Console\Event\Console_Terminate_Event;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
use Symfony\Component\Finder\Finder;
use Throwable;
use WeakMap;
class Kernel implements Kernel_Contract
{
    use Interacts_With_Time;
    /**
     * The Symfony event dispatcher implementation.
     *
     * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface|null
     */
    protected $symfony_dispatcher;
    /**
     * The Artisan application instance.
     *
     * @var \Illuminate\Console\Application|null
     */
    protected $artisan;
    /**
     * The Artisan commands provided by the application.
     *
     * @var array
     */
    protected $commands = [];
    /**
     * The paths where Artisan commands should be automatically discovered.
     *
     * @var array
     */
    protected $command_paths = [];
    /**
     * The paths where Artisan "routes" should be automatically discovered.
     *
     * @var array
     */
    protected $command_route_paths = [];
    /**
     * Indicates if the Closure commands have been loaded.
     *
     * @var bool
     */
    protected $commands_loaded = false;
    /**
     * The commands paths that have been "loaded".
     *
     * @var array
     */
    protected $loaded_paths = [];
    /**
     * All of the registered command duration handlers.
     *
     * @var array
     */
    protected $command_lifecycle_duration_handlers = [];
    /**
     * When the currently handled command started.
     *
     * @var \Illuminate\Support\Carbon|null
     */
    protected $command_started_at;
    /**
     * The bootstrap classes for the application.
     *
     * @var string[]
     */
    protected $bootstrappers = [\Illuminate\Foundation\Bootstrap\Load_Environment_Variables::class, \Illuminate\Foundation\Bootstrap\Load_Configuration::class, \Illuminate\Foundation\Bootstrap\Handle_Exceptions::class, \Illuminate\Foundation\Bootstrap\Register_Facades::class, \Illuminate\Foundation\Bootstrap\Set_Request_For_Console::class, \Illuminate\Foundation\Bootstrap\Register_Providers::class, \Illuminate\Foundation\Bootstrap\Boot_Providers::class];
    /**
     * Create a new console kernel instance.
     */
    public function __construct(
        /**
         * The application implementation.
         */
        protected \Illuminate\Contracts\Foundation\Application $app,
        /**
         * The event dispatcher implementation.
         */
        protected \Illuminate\Contracts\Events\Dispatcher $events
    )
    {
        if (!defined('ARTISAN_BINARY')) {
            define('ARTISAN_BINARY', 'artisan');
        }
        $this->app->booted(function (): void {
            if (!$this->app->running_unit_tests()) {
                $this->reroute_symfony_command_events();
            }
        });
    }
    /**
     * Re-route the Symfony command events to their Laravel counterparts.
     *
     * @internal
     *
     * @return $this
     */
    public function reroute_symfony_command_events(): static
    {
        if (is_null($this->symfony_dispatcher)) {
            $this->symfony_dispatcher = new Event_Dispatcher();
            $this->symfony_dispatcher->add_listener(Console_Events::COMMAND, function (Console_Command_Event $event): void {
                $this->events->dispatch(new Command_Starting($event->get_command()?->get_name() ?? '', $event->get_input(), $event->get_output()));
            });
            $this->symfony_dispatcher->add_listener(Console_Events::TERMINATE, function (Console_Terminate_Event $event): void {
                $this->events->dispatch(new Command_Finished($event->get_command()?->get_name() ?? '', $event->get_input(), $event->get_output(), $event->get_exit_code()));
            });
        }
        return $this;
    }
    /**
     * Run the console application.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $output
     */
    public function handle($input, $output = null): int
    {
        $this->command_started_at = Carbon::now();
        try {
            if (in_array($input->get_first_argument(), ['env:encrypt', 'env:decrypt'], true)) {
                $this->bootstrap_without_booting_providers();
            }
            $this->bootstrap();
            return $this->get_artisan()->run($input, $output);
        } catch (Throwable $e) {
            $this->report_exception($e);
            $this->render_exception($output, $e);
            return 1;
        }
    }
    /**
     * Terminate the application.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  int  $status
     */
    public function terminate($input, $status): void
    {
        $this->events->dispatch(new Terminating());
        $this->app->terminate();
        if ($this->command_started_at === null) {
            return;
        }
        $this->command_started_at->set_timezone($this->app['config']->get('app.timezone') ?? 'UTC');
        foreach ($this->command_lifecycle_duration_handlers as ['threshold' => $threshold, 'handler' => $handler]) {
            $end ??= Carbon::now();
            if ($this->command_started_at->diff_in_milliseconds($end) > $threshold) {
                $handler($this->command_started_at, $input, $status);
            }
        }
        $this->command_started_at = null;
    }
    /**
     * Register a callback to be invoked when the command lifecycle duration exceeds a given amount of time.
     *
     * @param  \DateTimeInterface|\Carbon\CarbonInterval|float|int  $threshold
     * @param  callable  $handler
     */
    public function when_command_lifecycle_is_longer_than($threshold, $handler): void
    {
        $threshold = $threshold instanceof DateTimeInterface ? $this->seconds_until($threshold) * 1000 : $threshold;
        $threshold = $threshold instanceof Carbon_Interval ? $threshold->total_milliseconds : $threshold;
        $this->command_lifecycle_duration_handlers[] = ['threshold' => $threshold, 'handler' => $handler];
    }
    /**
     * When the command being handled started.
     *
     * @return \Illuminate\Support\Carbon|null
     */
    public function command_started_at()
    {
        return $this->command_started_at;
    }
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
    }
    /**
     * Resolve a console schedule instance.
     *
     * @return \Illuminate\Console\Scheduling\Schedule
     */
    public function resolve_console_schedule()
    {
        return tap(new Schedule($this->schedule_timezone()), function ($schedule): void {
            $this->schedule($schedule->use_cache($this->schedule_cache()));
        });
    }
    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return \DateTimeZone|string|null
     */
    protected function schedule_timezone()
    {
        $config = $this->app['config'];
        return $config->get('app.schedule_timezone', $config->get('app.timezone'));
    }
    /**
     * Get the name of the cache store that should manage scheduling mutexes.
     *
     * @return string|null
     */
    protected function schedule_cache()
    {
        return $this->app['config']->get('cache.schedule_store', Env::get('SCHEDULE_CACHE_DRIVER', fn() => Env::get('SCHEDULE_CACHE_STORE')));
    }
    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
    }
    /**
     * Register a Closure based command with the application.
     *
     * @param  string  $signature
     */
    public function command($signature, Closure $callback): \Illuminate\Foundation\Console\Closure_Command
    {
        $command = new Closure_Command($signature, $callback);
        Artisan::starting(function ($artisan) use ($command): void {
            $artisan->add($command);
        });
        return $command;
    }
    /**
     * Register all of the commands in the given directory.
     *
     * @param  array|string  $paths
     * @return void
     */
    protected function load($paths)
    {
        $paths = array_unique(Arr::wrap($paths));
        $paths = array_filter($paths, is_dir(...));
        if (empty($paths)) {
            return;
        }
        $this->loaded_paths = array_values(array_unique(array_merge($this->loaded_paths, $paths)));
        $namespace = $this->app->get_namespace();
        $possible_commands = new WeakMap();
        $filter_commands = function (Spl_File_Info $file) use ($namespace, &$possible_commands): bool {
            $command_class_name = $this->command_class_from_file($file, $namespace);
            $possible_commands[$file] = $command_class_name;
            $command = rescue(fn(): \ReflectionClass => new ReflectionClass($command_class_name), null, false);
            return $command instanceof ReflectionClass && $command->is_sub_class_of(Command::class) && !$command->is_abstract();
        };
        foreach ($this->find_commands($paths)->filter($filter_commands) as $file) {
            Artisan::starting(function ($artisan) use ($file, $possible_commands): void {
                $artisan->resolve($possible_commands[$file]);
            });
        }
    }
    /**
     * Get the Finder instance for discovering command files.
     */
    protected function find_commands(array $paths): \Symfony\Component\Finder\Finder
    {
        return Finder::create()->in($paths)->name('*.php')->files();
    }
    /**
     * Extract the command class name from the given file path.
     */
    protected function command_class_from_file(Spl_File_Info $file, string $namespace): string
    {
        return $namespace . str_replace(['/', '.php'], ['\\', ''], Str::after($file->get_real_path(), realpath(app_path()) . DIRECTORY_SEPARATOR));
    }
    /**
     * Register the given command with the console application.
     */
    public function register_command(\Symfony\Component\Console\Command\Command $command): void
    {
        $this->get_artisan()->add($command);
    }
    /**
     * Run an Artisan console command by name.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $outputBuffer
     *
     * @throws \Symfony\Component\Console\Exception\CommandNotFoundException
     */
    public function call($command, array $parameters = [], $output_buffer = null): int
    {
        if (in_array($command, ['env:encrypt', 'env:decrypt'], true)) {
            $this->bootstrap_without_booting_providers();
        }
        $this->bootstrap();
        return $this->get_artisan()->call($command, $parameters, $output_buffer);
    }
    /**
     * Queue the given console command.
     *
     * @param  string  $command
     */
    public function queue($command, array $parameters = []): \Illuminate\Foundation\Bus\Pending_Dispatch
    {
        return Queued_Command::dispatch(func_get_args());
    }
    /**
     * Get all of the commands registered with the console.
     */
    public function all(): array
    {
        $this->bootstrap();
        return $this->get_artisan()->all();
    }
    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        $this->bootstrap();
        return $this->get_artisan()->output();
    }
    /**
     * Bootstrap the application for artisan commands.
     */
    public function bootstrap(): void
    {
        if (!$this->app->has_been_bootstrapped()) {
            $this->app->bootstrap_with($this->bootstrappers());
        }
        $this->app->load_deferred_providers();
        if (!$this->commands_loaded) {
            $this->commands();
            if ($this->should_discover_commands()) {
                $this->discover_commands();
            }
            $this->commands_loaded = true;
        }
    }
    /**
     * Discover the commands that should be automatically loaded.
     *
     * @return void
     */
    protected function discover_commands()
    {
        foreach ($this->command_paths as $path) {
            $this->load($path);
        }
        foreach ($this->command_route_paths as $path) {
            if (file_exists($path)) {
                require $path;
            }
        }
    }
    /**
     * Bootstrap the application without booting service providers.
     */
    public function bootstrap_without_booting_providers(): void
    {
        $this->app->bootstrap_with((new Collection($this->bootstrappers()))->reject(fn($bootstrapper): bool => $bootstrapper === \Illuminate\Foundation\Bootstrap\Boot_Providers::class)->all());
    }
    /**
     * Determine if the kernel should discover commands.
     */
    protected function should_discover_commands(): bool
    {
        return static::class === self::class;
    }
    /**
     * Get the Artisan application instance.
     *
     * @return \Illuminate\Console\Application
     */
    protected function get_artisan()
    {
        if (is_null($this->artisan)) {
            $this->artisan = (new Artisan($this->app, $this->events, $this->app->version()))->resolve_commands($this->commands)->set_container_command_loader();
            if ($this->symfony_dispatcher instanceof Event_Dispatcher) {
                $this->artisan->set_dispatcher($this->symfony_dispatcher);
                $this->artisan->set_signals_to_dispatch_event();
            }
        }
        return $this->artisan;
    }
    /**
     * Set the Artisan application instance.
     *
     * @param  \Illuminate\Console\Application|null  $artisan
     */
    public function set_artisan($artisan): void
    {
        $this->artisan = $artisan;
    }
    /**
     * Set the Artisan commands provided by the application.
     *
     * @return $this
     */
    public function add_commands(array $commands): static
    {
        $this->commands = array_values(array_unique(array_merge($this->commands, $commands)));
        return $this;
    }
    /**
     * Set the paths that should have their Artisan commands automatically discovered.
     *
     * @return $this
     */
    public function add_command_paths(array $paths): static
    {
        $this->command_paths = array_values(array_unique(array_merge($this->command_paths, $paths)));
        return $this;
    }
    /**
     * Set the paths that should have their Artisan "routes" automatically discovered.
     *
     * @return $this
     */
    public function add_command_route_paths(array $paths): static
    {
        $this->command_route_paths = array_values(array_unique(array_merge($this->command_route_paths, $paths)));
        return $this;
    }
    /**
     * Get the bootstrap classes for the application.
     *
     * @return array
     */
    protected function bootstrappers()
    {
        return $this->bootstrappers;
    }
    /**
     * Report the exception to the exception handler.
     *
     * @return void
     */
    protected function report_exception(Throwable $e)
    {
        $this->app[Exception_Handler::class]->report($e);
    }
    /**
     * Render the given exception.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function render_exception($output, Throwable $e)
    {
        $this->app[Exception_Handler::class]->render_for_console($output, $e);
    }
}