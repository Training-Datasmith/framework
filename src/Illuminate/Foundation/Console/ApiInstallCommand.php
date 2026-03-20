<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use function Illuminate\Support\artisan_binary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use function Illuminate\Support\php_binary;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'install:api')]
class Api_Install_Command extends Command
{
    use Interacts_With_Composer_Packages;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:api
                    {--composer=global : Absolute path to the Composer binary which should be used to install packages}
                    {--force : Overwrite any existing API routes file}
                    {--passport : Install Laravel Passport instead of Laravel Sanctum}
                    {--without-migration-prompt : Do not prompt to run pending migrations}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an API routes file and install Laravel Sanctum or Laravel Passport';
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('passport')) {
            $this->install_passport();
        } else {
            $this->install_sanctum();
        }
        if (file_exists($api_routes_path = $this->laravel->base_path('routes/api.php')) && !$this->option('force')) {
            $this->components->error('API routes file already exists.');
        } else {
            $this->components->info('Published API routes file.');
            copy(__DIR__ . '/stubs/api-routes.stub', $api_routes_path);
            if ($this->option('passport')) {
                (new Filesystem())->replace_in_file('auth:sanctum', 'auth:api', $api_routes_path);
            }
            $this->uncomment_api_routes_file();
        }
        if ($this->option('passport')) {
            Process::run([php_binary(), artisan_binary(), 'passport:install']);
            $this->components->info('API scaffolding installed. Please add the [Laravel\Passport\HasApiTokens] trait to your User model.');
        } else {
            if (!$this->option('without-migration-prompt')) {
                if ($this->confirm('One new database migration has been published. Would you like to run all pending database migrations?', true)) {
                    $this->call('migrate');
                }
            }
            $this->components->info('API scaffolding installed. Please add the [Laravel\Sanctum\HasApiTokens] trait to your User model.');
        }
    }
    /**
     * Uncomment the API routes file in the application bootstrap file.
     *
     * @return void
     */
    protected function uncomment_api_routes_file()
    {
        $app_bootstrap_path = $this->laravel->bootstrap_path('app.php');
        $content = file_get_contents($app_bootstrap_path);
        if (str_contains($content, '// api: ')) {
            (new Filesystem())->replace_in_file('// api: ', 'api: ', $app_bootstrap_path);
        } elseif (str_contains($content, 'web: __DIR__.\'/../routes/web.php\',')) {
            (new Filesystem())->replace_in_file('web: __DIR__.\'/../routes/web.php\',', 'web: __DIR__.\'/../routes/web.php\',' . PHP_EOL . '        api: __DIR__.\'/../routes/api.php\',', $app_bootstrap_path);
        } else {
            $this->components->warn("Unable to automatically add API route definition to [{$app_bootstrap_path}]. API route file should be registered manually.");
            return;
        }
    }
    /**
     * Install Laravel Sanctum into the application.
     *
     * @return void
     */
    protected function install_sanctum()
    {
        $this->require_composer_packages($this->option('composer'), ['laravel/sanctum:^4.0']);
        $migration_published = (new Collection(scandir($this->laravel->database_path('migrations'))))->contains(fn($migration): int|false => preg_match('/\d{4}_\d{2}_\d{2}_\d{6}_create_personal_access_tokens_table.php/', (string) $migration));
        if (!$migration_published) {
            Process::run([php_binary(), artisan_binary(), 'vendor:publish', '--provider', 'Laravel\Sanctum\SanctumServiceProvider']);
        }
    }
    /**
     * Install Laravel Passport into the application.
     *
     * @return void
     */
    protected function install_passport()
    {
        $this->require_composer_packages($this->option('composer'), ['laravel/passport:^13.0']);
    }
}