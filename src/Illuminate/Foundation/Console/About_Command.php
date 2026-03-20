<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'about')]
class About_Command extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'about {--only= : The section to display}
                {--json : Output the information as JSON}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display basic information about your application';
    /**
     * The data to display.
     *
     * @var array
     */
    protected static $data = [];
    /**
     * The registered callables that add custom data to the command output.
     *
     * @var array
     */
    protected static $custom_data_resolvers = [];
    /**
     * Create a new command instance.
     */
    public function __construct(
        /**
         * The Composer instance.
         */
        protected \Illuminate\Support\Composer $composer
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->gather_application_information();
        (new Collection(static::$data))->map(fn($items): \Illuminate\Support\Collection => (new Collection($items))->map(function ($value) {
            if (is_array($value)) {
                return [$value];
            }
            if (is_string($value)) {
                $value = $this->laravel->make($value);
            }
            return (new Collection($this->laravel->call($value)))->map(fn($value, $key): array => [$key, $value])->values()->all();
        })->flatten(1))->sort_by(function ($data, $key): int|string {
            $index = array_search($key, ['Environment', 'Cache', 'Drivers']);
            return $index === false ? 99 : $index;
        })->filter(fn($data, $key): bool => $this->option('only') ? in_array($this->to_search_keyword($key), $this->sections()) : true)->pipe(fn($data) => $this->display($data));
        $this->new_line();
        return 0;
    }
    /**
     * Display the application information.
     *
     * @param  \Illuminate\Support\Collection  $data
     * @return void
     */
    protected function display($data)
    {
        $this->option('json') ? $this->display_json($data) : $this->display_detail($data);
    }
    /**
     * Display the application information as a detail view.
     *
     * @param  \Illuminate\Support\Collection  $data
     * @return void
     */
    protected function display_detail($data)
    {
        $data->each(function ($data, string $section): void {
            $this->new_line();
            $this->components->two_column_detail('  <fg=green;options=bold>' . $section . '</>');
            $data->pipe(fn($data) => $section !== 'Environment' ? $data->sort() : $data)->each(function ($detail): void {
                [$label, $value] = $detail;
                $this->components->two_column_detail($label, value($value, false));
            });
        });
    }
    /**
     * Display the application information as JSON.
     *
     * @param  \Illuminate\Support\Collection  $data
     * @return void
     */
    protected function display_json($data)
    {
        $output = $data->flat_map(fn($data, $section): array => [(new Stringable($section))->snake()->value() => $data->map_with_keys(fn($item, $key): array => [$this->to_search_keyword($item[0]) => value($item[1], true)])]);
        $this->output->writeln(strip_tags(json_encode($output)));
    }
    /**
     * Gather information about the application.
     *
     * @return void
     */
    protected function gather_application_information()
    {
        self::$data = [];
        $format_enabled_status = fn($value): string => $value ? '<fg=yellow;options=bold>ENABLED</>' : 'OFF';
        $format_cached_status = fn($value): string => $value ? '<fg=green;options=bold>CACHED</>' : '<fg=yellow;options=bold>NOT CACHED</>';
        $format_storage_linked_status = fn($value): string => $value ? '<fg=green;options=bold>LINKED</>' : '<fg=yellow;options=bold>NOT LINKED</>';
        static::add_to_section('Environment', fn(): array => ['Application Name' => config('app.name'), 'Laravel Version' => $this->laravel->version(), 'PHP Version' => phpversion(), 'Composer Version' => $this->composer->get_version() ?? '<fg=yellow;options=bold>-</>', 'Environment' => $this->laravel->environment(), 'Debug Mode' => static::format(config('app.debug'), console: $format_enabled_status), 'URL' => Str::of(config('app.url'))->replace(['http://', 'https://'], ''), 'Maintenance Mode' => static::format($this->laravel->is_down_for_maintenance(), console: $format_enabled_status), 'Timezone' => config('app.timezone'), 'Locale' => config('app.locale')]);
        static::add_to_section('Cache', fn(): array => ['Config' => static::format($this->laravel->configuration_is_cached(), console: $format_cached_status), 'Events' => static::format($this->laravel->events_are_cached(), console: $format_cached_status), 'Routes' => static::format($this->laravel->routes_are_cached(), console: $format_cached_status), 'Views' => static::format($this->has_php_files(config('view.compiled')), console: $format_cached_status)]);
        static::add_to_section('Drivers', fn(): array => array_filter(['Broadcasting' => config('broadcasting.default'), 'Cache' => function ($json) {
            $cache_store = config('cache.default');
            if (config('cache.stores.' . $cache_store . '.driver') === 'failover') {
                $secondary = new Collection(config('cache.stores.' . $cache_store . '.stores'));
                return value(static::format(value: $cache_store, console: fn($value): string => '<fg=yellow;options=bold>' . $value . '</> <fg=gray;options=bold>/</> ' . $secondary->implode(', '), json: fn() => $secondary->all()), $json);
            }
            return $cache_store;
        }, 'Database' => config('database.default'), 'Logs' => function ($json) {
            $log_channel = config('logging.default');
            if (config('logging.channels.' . $log_channel . '.driver') === 'stack') {
                $secondary = new Collection(config('logging.channels.' . $log_channel . '.channels'));
                return value(static::format(value: $log_channel, console: fn($value): string => '<fg=yellow;options=bold>' . $value . '</> <fg=gray;options=bold>/</> ' . $secondary->implode(', '), json: fn() => $secondary->all()), $json);
            }
            return $log_channel;
        }, 'Mail' => function ($json) {
            $mail_mailer = config('mail.default');
            if (in_array(config('mail.mailers.' . $mail_mailer . '.transport'), ['failover', 'roundrobin'])) {
                $secondary = new Collection(config('mail.mailers.' . $mail_mailer . '.mailers'));
                return value(static::format(value: $mail_mailer, console: fn($value): string => '<fg=yellow;options=bold>' . $value . '</> <fg=gray;options=bold>/</> ' . $secondary->implode(', '), json: fn() => $secondary->all()), $json);
            }
            return $mail_mailer;
        }, 'Octane' => config('octane.server'), 'Queue' => function ($json) {
            $queue_connection = config('queue.default');
            if (config('queue.connections.' . $queue_connection . '.driver') === 'failover') {
                $secondary = new Collection(config('queue.connections.' . $queue_connection . '.connections'));
                return value(static::format(value: $queue_connection, console: fn($value): string => '<fg=yellow;options=bold>' . $value . '</> <fg=gray;options=bold>/</> ' . $secondary->implode(', '), json: fn() => $secondary->all()), $json);
            }
            return $queue_connection;
        }, 'Scout' => config('scout.driver'), 'Session' => config('session.driver')]));
        static::add_to_section('Storage', fn(): array => [...$this->determine_storage_path_link_status($format_storage_linked_status)]);
        (new Collection(static::$custom_data_resolvers))->each->__invoke();
    }
    /**
     * Determine storage symbolic links status.
     *
     * @return array<string,mixed>
     */
    protected function determine_storage_path_link_status(callable $format_storage_linked_status): array
    {
        return (new Collection(config('filesystems.links', [])))->map_with_keys(function ($target, $link) use ($format_storage_linked_status): array {
            $path = Str::replace(public_path(), '', $link);
            return [public_path($path) => static::format(file_exists($link), console: $format_storage_linked_status)];
        })->to_array();
    }
    /**
     * Determine whether the given directory has PHP files.
     */
    protected function has_php_files(string $path): bool
    {
        return count(glob($path . '/*.php')) > 0;
    }
    /**
     * Add additional data to the output of the "about" command.
     *
     * @param  callable|string|array  $data
     */
    public static function add(string $section, $data, ?string $value = null): void
    {
        static::$custom_data_resolvers[] = fn() => static::add_to_section($section, $data, $value);
    }
    /**
     * Add additional data to the output of the "about" command.
     *
     * @param  callable|string|array  $data
     * @return void
     */
    protected static function add_to_section(string $section, $data, ?string $value = null)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                self::$data[$section][] = [$key, $value];
            }
        } elseif (is_callable($data) || $value === null && class_exists($data)) {
            self::$data[$section][] = $data;
        } else {
            self::$data[$section][] = [$data, $value];
        }
    }
    /**
     * Get the sections provided to the command.
     *
     * @return array
     */
    protected function sections()
    {
        return (new Collection(explode(',', $this->option('only') ?? '')))->filter()->map(fn(string $only) => $this->to_search_keyword($only))->all();
    }
    /**
     * Materialize a function that formats a given value for CLI or JSON output.
     *
     * @param  mixed  $value
     * @param  (\Closure(mixed):(mixed))|null  $console
     * @param  (\Closure(mixed):(mixed))|null  $json
     * @return \Closure(bool):mixed
     */
    public static function format($value, ?Closure $console = null, ?Closure $json = null)
    {
        return function ($is_json) use ($value, $console, $json) {
            if ($is_json === true && $json instanceof Closure) {
                return value($json, $value);
            }
            if ($is_json === false && $console instanceof Closure) {
                return value($console, $value);
            }
            return value($value);
        };
    }
    /**
     * Format the given string for searching.
     *
     * @return string
     */
    protected function to_search_keyword(string $value)
    {
        return (new Stringable($value))->lower()->snake()->value();
    }
    /**
     * Flush the registered about data.
     */
    public static function flush_state(): void
    {
        static::$data = [];
        static::$custom_data_resolvers = [];
    }
}