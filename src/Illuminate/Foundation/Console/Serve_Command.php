<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Illuminate\Support\Interacts_With_Time;
use function Illuminate\Support\php_binary;
use Illuminate\Support\Stringable;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Process\Process;
use function Termwind\terminal;
#[As_Command(name: 'serve')]
class Serve_Command extends Command
{
    use Interacts_With_Time;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'serve';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Serve the application on the PHP development server';
    /**
     * The number of PHP CLI server workers.
     *
     * @var int<2, max>|false
     */
    protected $php_server_workers = 1;
    /**
     * The current port offset.
     *
     * @var int
     */
    protected $port_offset = 0;
    /**
     * The list of lines that are pending to be output.
     *
     * @var string
     */
    protected $output_buffer = '';
    /**
     * The list of requests being handled and their start time.
     *
     * @var array<int, \Illuminate\Support\Carbon>
     */
    protected $requests_pool;
    /**
     * Indicates if the "Server running on..." output message has been displayed.
     *
     * @var bool
     */
    protected $server_running_has_been_displayed = false;
    /**
     * The environment variables that should be passed from host machine to the PHP server process.
     *
     * @var string[]
     */
    public static $passthrough_variables = ['APP_ENV', 'HERD_PHP_81_INI_SCAN_DIR', 'HERD_PHP_82_INI_SCAN_DIR', 'HERD_PHP_83_INI_SCAN_DIR', 'HERD_PHP_84_INI_SCAN_DIR', 'HERD_PHP_85_INI_SCAN_DIR', 'IGNITION_LOCAL_SITES_PATH', 'LARAVEL_SAIL', 'PATH', 'PHP_IDE_CONFIG', 'SYSTEMROOT', 'XDEBUG_CONFIG', 'XDEBUG_MODE', 'XDEBUG_SESSION'];
    /** {@inheritdoc} */
    #[\Override]
    protected function initialize(Input_Interface $input, Output_Interface $output): void
    {
        $this->php_server_workers = transform((int) env('PHP_CLI_SERVER_WORKERS', 1), function (int $workers): false|int {
            if ($workers < 2) {
                return false;
            }
            if ($workers > 1 && !$this->option('no-reload') && !(int) env('LARAVEL_SAIL', 0)) {
                $this->components->warn('Unable to respect the `PHP_CLI_SERVER_WORKERS` environment variable without the `--no-reload` flag. Only creating a single server.');
                return false;
            }
            return $workers;
        });
        parent::initialize($input, $output);
    }
    /**
     * Execute the console command.
     *
     * @return int
     *
     * @throws \Exception
     */
    public function handle()
    {
        $environment_file = $this->option('env') ? base_path('.env') . '.' . $this->option('env') : base_path('.env');
        $has_environment = file_exists($environment_file);
        $environment_last_modified = $has_environment ? filemtime($environment_file) : now()->add_days(30)->get_timestamp();
        $process = $this->start_process($has_environment);
        while ($process->is_running()) {
            if ($has_environment) {
                clearstatcache(false, $environment_file);
            }
            if (!$this->option('no-reload') && $has_environment && filemtime($environment_file) > $environment_last_modified) {
                $environment_last_modified = filemtime($environment_file);
                $this->new_line();
                $this->components->info('Environment modified. Restarting server...');
                $process->stop(5);
                $this->server_running_has_been_displayed = false;
                $process = $this->start_process($has_environment);
            }
            usleep(500 * 1000);
        }
        $status = $process->get_exit_code();
        if ($status && $this->can_try_another_port()) {
            $this->port_offset += 1;
            return $this->handle();
        }
        return $status;
    }
    /**
     * Start a new server process.
     *
     * @param  bool  $hasEnvironment
     */
    protected function start_process($has_environment): \Symfony\Component\Process\Process
    {
        $process = new Process($this->server_command(), public_path(), (new Collection($_ENV))->map_with_keys(function ($value, $key) use ($has_environment): array {
            if ($this->option('no-reload') || !$has_environment) {
                return [$key => $value];
            }
            return in_array($key, static::$passthrough_variables) ? [$key => $value] : [$key => false];
        })->merge(['PHP_CLI_SERVER_WORKERS' => $this->php_server_workers])->all());
        $this->trap(fn(): array => [SIGTERM, SIGINT, SIGHUP, SIGUSR1, SIGUSR2, SIGQUIT], function (?int $signal) use ($process): void {
            if ($process->is_running()) {
                $process->stop(10, $signal);
            }
            exit;
        });
        $process->start($this->handle_process_output());
        return $process;
    }
    /**
     * Get the full server command.
     */
    protected function server_command(): array
    {
        $server = file_exists(base_path('server.php')) ? base_path('server.php') : __DIR__ . '/../resources/server.php';
        return [php_binary(), '-S', $this->host() . ':' . $this->port(), $server];
    }
    /**
     * Get the host for the command.
     *
     * @return string
     */
    protected function host()
    {
        [$host] = $this->get_host_and_port();
        return $host;
    }
    /**
     * Get the port for the command.
     *
     * @return string
     */
    protected function port(): float|int|array
    {
        $port = $this->input->get_option('port');
        if (is_null($port)) {
            [, $port] = $this->get_host_and_port();
        }
        $port = $port ?: 8000;
        return $port + $this->port_offset;
    }
    /**
     * Get the host and port from the host option string.
     */
    protected function get_host_and_port(): array
    {
        if (preg_match('/(\[.*\]):?([0-9]+)?/', (string) $this->input->get_option('host'), $matches) !== false) {
            return [$matches[1] ?? $this->input->get_option('host'), $matches[2] ?? null];
        }
        $host_parts = explode(':', (string) $this->input->get_option('host'));
        return [$host_parts[0], $host_parts[1] ?? null];
    }
    /**
     * Check if the command has reached its maximum number of port tries.
     */
    protected function can_try_another_port(): bool
    {
        return is_null($this->input->get_option('port')) && $this->input->get_option('tries') > $this->port_offset;
    }
    /**
     * Returns a "callable" to handle the process output.
     *
     * @return callable(string, string): void
     */
    protected function handle_process_output()
    {
        return function ($type, string $buffer): void {
            $this->output_buffer .= $buffer;
            $this->flush_output_buffer();
        };
    }
    /**
     * Flush the output buffer.
     *
     * @return void
     */
    protected function flush_output_buffer()
    {
        $lines = (new Stringable($this->output_buffer))->explode("\n");
        $this->output_buffer = (string) $lines->pop();
        $lines->map(fn($line): string => trim($line))->filter()->each(function ($line): void {
            if ((new Stringable($line))->contains('Development Server (http')) {
                if ($this->server_running_has_been_displayed === false) {
                    $this->server_running_has_been_displayed = true;
                    $this->components->info("Server running on [http://{$this->host()}:{$this->port()}].");
                    $this->comment('  <fg=yellow;options=bold>Press Ctrl+C to stop the server</>');
                    $this->new_line();
                }
                return;
            }
            if ((new Stringable($line))->contains(' Accepted')) {
                $request_port = static::get_request_port_from_line($line);
                $this->requests_pool[$request_port] = [$this->get_date_from_line($line), $this->requests_pool[$request_port][1] ?? false, microtime(true)];
            } elseif ((new Stringable($line))->contains([' [200]: GET '])) {
                $request_port = static::get_request_port_from_line($line);
                $this->requests_pool[$request_port][1] = trim(explode('[200]: GET', $line)[1]);
            } elseif ((new Stringable($line))->contains('URI:')) {
                $request_port = static::get_request_port_from_line($line);
                $this->requests_pool[$request_port][1] = trim(explode('URI: ', $line)[1]);
            } elseif ((new Stringable($line))->contains(' Closing')) {
                $request_port = static::get_request_port_from_line($line);
                if (empty($this->requests_pool[$request_port]) || count($this->requests_pool[$request_port] ?? []) !== 3) {
                    $this->requests_pool[$request_port] = [$this->get_date_from_line($line), false, microtime(true)];
                }
                [$start_date, $file, $start_microtime] = $this->requests_pool[$request_port];
                $formatted_started_at = $start_date->format('Y-m-d H:i:s');
                unset($this->requests_pool[$request_port]);
                [$date, $time] = explode(' ', $formatted_started_at);
                $this->output->write("  <fg=gray>{$date}</> {$time}");
                $run_time = $this->run_time_for_humans($start_microtime);
                if ($file) {
                    $this->output->write($file = " {$file}");
                }
                $dots = max(terminal()->width() - mb_strlen($formatted_started_at) - mb_strlen($file) - mb_strlen($run_time) - 9, 0);
                $this->output->write(' ' . str_repeat('<fg=gray>.</>', $dots));
                $this->output->writeln(" <fg=gray>~ {$run_time}</>");
            } elseif ((new Stringable($line))->contains(['Closed without sending a request', 'Failed to poll event'])) {
                // ...
            } elseif (!empty($line)) {
                if ((new Stringable($line))->starts_with('[')) {
                    $line = (new Stringable($line))->after('] ');
                }
                $this->output->writeln("  <fg=gray>{$line}</>");
            }
        });
    }
    /**
     * Get the date from the given PHP server output.
     *
     * @param  string  $line
     * @return \Illuminate\Support\Carbon
     */
    protected function get_date_from_line($line)
    {
        $regex = !windows_os() && is_int($this->php_server_workers) ? '/^\[\d+]\s\[([a-zA-Z0-9: ]+)\]/' : '/^\[([^\]]+)\]/';
        $line = str_replace('  ', ' ', $line);
        preg_match($regex, $line, $matches);
        return Carbon::create_from_format('D M d H:i:s Y', $matches[1]);
    }
    /**
     * Get the request port from the given PHP server output.
     *
     * @param  string  $line
     */
    public static function get_request_port_from_line($line): int
    {
        preg_match('/(\[\w+\s\w+\s\d+\s[\d:]+\s\d{4}\]\s)?:(\d+)\s(?:(?:\w+$)|(?:\[.*))/', $line, $matches);
        if (!isset($matches[2])) {
            throw new \InvalidArgumentException("Failed to extract the request port. Ensure the log line contains a valid port: {$line}");
        }
        return (int) $matches[2];
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['host', null, Input_Option::VALUE_OPTIONAL, 'The host address to serve the application on', Env::get('SERVER_HOST', '127.0.0.1')], ['port', null, Input_Option::VALUE_OPTIONAL, 'The port to serve the application on', Env::get('SERVER_PORT')], ['tries', null, Input_Option::VALUE_OPTIONAL, 'The max number of ports to attempt to serve from', 10], ['no-reload', null, Input_Option::VALUE_NONE, 'Do not reload the development server on .env file changes']];
    }
}