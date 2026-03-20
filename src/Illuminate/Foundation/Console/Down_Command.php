<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use App\Http\Middleware\Prevent_Requests_During_Maintenance as AppPreventRequestsDuringMaintenance;
use DateTimeInterface;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Foundation\Events\Maintenance_Mode_Enabled;
use Illuminate\Foundation\Exceptions\Register_Error_View_Paths;
use Illuminate\Foundation\Http\Middleware\Prevent_Requests_During_Maintenance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\As_Command;
use Throwable;
#[As_Command(name: 'down')]
class Down_Command extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'down {--redirect= : The path that users should be redirected to}
                                 {--render= : The view that should be prerendered for display during maintenance mode}
                                 {--retry= : The number of seconds or the datetime after which the request may be retried}
                                 {--refresh= : The number of seconds after which the browser may refresh}
                                 {--secret= : The secret phrase that may be used to bypass maintenance mode}
                                 {--with-secret : Generate a random secret phrase that may be used to bypass maintenance mode}
                                 {--status=503 : The status code that should be used when returning the maintenance mode response}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Put the application into maintenance / demo mode';
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $was_already_down = $this->laravel->maintenance_mode()->active();
            $down_file_payload = $this->get_down_file_payload();
            $this->laravel->maintenance_mode()->activate($down_file_payload);
            file_put_contents(storage_path('framework/maintenance.php'), file_get_contents(__DIR__ . '/stubs/maintenance-mode.stub'));
            $this->laravel->get('events')->dispatch(new Maintenance_Mode_Enabled());
            $this->components->info($was_already_down ? 'Maintenance mode options updated.' : 'Application is now in maintenance mode.');
            if ($down_file_payload['secret'] !== null) {
                $this->components->info('You may bypass maintenance mode via [' . config('app.url') . "/{$down_file_payload['secret']}].");
            }
        } catch (Exception $e) {
            $this->components->error(sprintf('Failed to enter maintenance mode: %s.', $e->get_message()));
            return 1;
        }
    }
    /**
     * Get the payload to be placed in the "down" file.
     */
    protected function get_down_file_payload(): array
    {
        return ['except' => $this->excluded_paths(), 'redirect' => $this->redirect_path(), 'retry' => $this->get_retry_time(), 'refresh' => $this->option('refresh'), 'secret' => $this->get_secret(), 'status' => (int) ($this->option('status') ?? 503), 'template' => $this->option('render') ? $this->prerender_view() : null];
    }
    /**
     * Get the paths that should be excluded from maintenance mode.
     *
     * @return array
     */
    protected function excluded_paths()
    {
        try {
            return $this->laravel->make(App_Prevent_Requests_During_Maintenance::class)->get_excluded_paths();
        } catch (Throwable) {
            try {
                return $this->laravel->make(Prevent_Requests_During_Maintenance::class)->get_excluded_paths();
            } catch (Throwable) {
                return [];
            }
        }
    }
    /**
     * Get the path that users should be redirected to.
     *
     * @return string
     */
    protected function redirect_path(): string|array|false|float|int|null
    {
        if ($this->option('redirect') && $this->option('redirect') !== '/') {
            return '/' . trim($this->option('redirect'), '/');
        }
        return $this->option('redirect');
    }
    /**
     * Prerender the specified view so that it can be rendered even before loading Composer.
     *
     * @return string
     */
    protected function prerender_view()
    {
        (new Register_Error_View_Paths())();
        return view($this->option('render'), ['retryAfter' => $this->option('retry')])->render();
    }
    /**
     * Get the number of seconds or date / time the client should wait before retrying their request.
     *
     * @return int|string|null
     */
    protected function get_retry_time()
    {
        $retry = $this->option('retry');
        if (is_numeric($retry) && $retry > 0) {
            return (int) $retry;
        }
        if (is_string($retry) && !empty($retry)) {
            try {
                $date = Carbon::parse($retry);
                return $date->format(DateTimeInterface::RFC7231);
            } catch (Exception) {
                return null;
            }
        }
        return null;
    }
    /**
     * Get the secret phrase that may be used to bypass maintenance mode.
     *
     * @return string|null
     */
    protected function get_secret()
    {
        return match (true) {
            !is_null($this->option('secret')) => $this->option('secret'),
            $this->option('with-secret') => Str::random(),
            default => null,
        };
    }
}