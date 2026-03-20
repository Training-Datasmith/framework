<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Composer\Installed_Versions;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use function Illuminate\Support\artisan_binary;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Process;
use function Illuminate\Support\php_binary;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'install:broadcasting')]
class Broadcasting_Install_Command extends Command
{
    use Interacts_With_Composer_Packages;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:broadcasting
                    {--composer=global : Absolute path to the Composer binary which should be used to install packages}
                    {--force : Overwrite any existing broadcasting routes file}
                    {--without-reverb : Do not prompt to install Laravel Reverb}
                    {--reverb : Install Laravel Reverb as the default broadcaster}
                    {--pusher : Install Pusher as the default broadcaster}
                    {--ably : Install Ably as the default broadcaster}
                    {--without-node : Do not prompt to install Node dependencies}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a broadcasting channel routes file';
    /**
     * The broadcasting driver to use.
     *
     * @var string|null
     */
    protected $driver;
    /**
     * The framework packages to install.
     *
     * @var array
     */
    protected $framework_packages = ['react' => '@laravel/echo-react', 'vue' => '@laravel/echo-vue'];
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->call('config:publish', ['name' => 'broadcasting']);
        // Install channel routes file...
        if (!file_exists($broadcasting_routes_path = $this->laravel->base_path('routes/channels.php')) || $this->option('force')) {
            $this->components->info("Published 'channels' route file.");
            copy(__DIR__ . '/stubs/broadcasting-routes.stub', $broadcasting_routes_path);
        }
        $this->uncomment_channels_routes_file();
        $this->enable_broadcast_service_provider();
        $this->driver = $this->resolve_driver();
        Env::write_variable('BROADCAST_CONNECTION', $this->driver, $this->laravel->base_path('.env'), true);
        $this->collect_driver_config();
        $this->install_driver_packages();
        if ($this->is_using_supported_framework()) {
            // If this is a supported framework, we will use the framework-specific Echo helpers...
            $this->inject_framework_specific_configuration();
        } else {
            // Standard JavaScript implementation...
            if (!file_exists($echo_script_path = $this->laravel->resource_path('js/echo.js'))) {
                if (!is_dir($directory = $this->laravel->resource_path('js'))) {
                    mkdir($directory, 0755, true);
                }
                $stub_path = __DIR__ . '/stubs/echo-js-' . $this->driver . '.stub';
                if (!file_exists($stub_path)) {
                    $stub_path = __DIR__ . '/stubs/echo-js-reverb.stub';
                }
                copy($stub_path, $echo_script_path);
            }
            // Only add the bootstrap import for the standard JS implementation...
            if (file_exists($bootstrap_script_path = $this->laravel->resource_path('js/bootstrap.js'))) {
                $bootstrap_script = file_get_contents($bootstrap_script_path);
                if (!str_contains($bootstrap_script, './echo')) {
                    file_put_contents($bootstrap_script_path, trim($bootstrap_script . PHP_EOL . file_get_contents(__DIR__ . '/stubs/echo-bootstrap-js.stub')) . PHP_EOL);
                }
            } elseif (file_exists($app_script_path = $this->laravel->resource_path('js/app.js'))) {
                // If no bootstrap.js, try app.js...
                $app_script = file_get_contents($app_script_path);
                if (!str_contains($app_script, './echo')) {
                    file_put_contents($app_script_path, trim($app_script . PHP_EOL . file_get_contents(__DIR__ . '/stubs/echo-bootstrap-js.stub')) . PHP_EOL);
                }
            }
        }
        $this->install_reverb();
        $this->install_node_dependencies();
    }
    /**
     * Uncomment the "channels" routes file in the application bootstrap file.
     *
     * @return void
     */
    protected function uncomment_channels_routes_file()
    {
        $app_bootstrap_path = $this->laravel->bootstrap_path('app.php');
        $content = file_get_contents($app_bootstrap_path);
        if (str_contains($content, '// channels: ')) {
            (new Filesystem())->replace_in_file('// channels: ', 'channels: ', $app_bootstrap_path);
        } elseif (str_contains($content, 'channels: ')) {
            return;
        } elseif (str_contains($content, 'commands: __DIR__.\'/../routes/console.php\',')) {
            (new Filesystem())->replace_in_file('commands: __DIR__.\'/../routes/console.php\',', 'commands: __DIR__.\'/../routes/console.php\',' . PHP_EOL . '        channels: __DIR__.\'/../routes/channels.php\',', $app_bootstrap_path);
        } elseif (str_contains($content, '->withRouting(')) {
            (new Filesystem())->replace_in_file('->withRouting(', '->withRouting(' . PHP_EOL . '        channels: __DIR__.\'/../routes/channels.php\',', $app_bootstrap_path);
        } else {
            $this->components->error('Unable to register broadcast routes. Please register them manually in [' . $app_bootstrap_path . '].');
        }
    }
    /**
     * Uncomment the "BroadcastServiceProvider" in the application configuration.
     *
     * @return void
     */
    protected function enable_broadcast_service_provider()
    {
        $filesystem = new Filesystem();
        if (!$filesystem->exists(app()->config_path('app.php')) || !$filesystem->exists('app/Providers/BroadcastServiceProvider.php')) {
            return;
        }
        $config = $filesystem->get(app()->config_path('app.php'));
        if (str_contains($config, '// App\Providers\BroadcastServiceProvider::class')) {
            $filesystem->replace_in_file('// App\Providers\BroadcastServiceProvider::class', 'App\Providers\BroadcastServiceProvider::class', app()->config_path('app.php'));
        }
    }
    /**
     * Collect the driver configuration.
     *
     * @return void
     */
    protected function collect_driver_config()
    {
        $env_path = $this->laravel->base_path('.env');
        if (!file_exists($env_path)) {
            return;
        }
        match ($this->driver) {
            'pusher' => $this->collect_pusher_config(),
            'ably' => $this->collect_ably_config(),
            default => null,
        };
    }
    /**
     * Install the driver packages.
     *
     * @return void
     */
    protected function install_driver_packages()
    {
        $package = match ($this->driver) {
            'pusher' => 'pusher/pusher-php-server',
            'ably' => 'ably/ably-php',
            default => null,
        };
        if (!$package || Installed_Versions::is_installed($package)) {
            return;
        }
        $this->require_composer_packages($this->option('composer'), [$package]);
    }
    /**
     * Collect the Pusher configuration.
     *
     * @return void
     */
    protected function collect_pusher_config()
    {
        $app_id = text('Pusher App ID', 'Enter your Pusher app ID');
        $key = password('Pusher App Key', 'Enter your Pusher app key');
        $secret = password('Pusher App Secret', 'Enter your Pusher app secret');
        $cluster = select('Pusher App Cluster', ['mt1', 'us2', 'us3', 'eu', 'ap1', 'ap2', 'ap3', 'ap4', 'sa1']);
        Env::write_variables(['PUSHER_APP_ID' => $app_id, 'PUSHER_APP_KEY' => $key, 'PUSHER_APP_SECRET' => $secret, 'PUSHER_APP_CLUSTER' => $cluster, 'PUSHER_PORT' => 443, 'PUSHER_SCHEME' => 'https', 'VITE_PUSHER_APP_KEY' => '${PUSHER_APP_KEY}', 'VITE_PUSHER_APP_CLUSTER' => '${PUSHER_APP_CLUSTER}', 'VITE_PUSHER_HOST' => '${PUSHER_HOST}', 'VITE_PUSHER_PORT' => '${PUSHER_PORT}', 'VITE_PUSHER_SCHEME' => '${PUSHER_SCHEME}'], $this->laravel->base_path('.env'));
    }
    /**
     * Collect the Ably configuration.
     *
     * @return void
     */
    protected function collect_ably_config()
    {
        $this->components->warn('Make sure to enable "Pusher protocol support" in your Ably app settings.');
        $key = password('Ably Key', 'Enter your Ably key');
        $public_key = explode(':', $key)[0] ?? $key;
        Env::write_variables(['ABLY_KEY' => $key, 'ABLY_PUBLIC_KEY' => $public_key, 'VITE_ABLY_PUBLIC_KEY' => '${ABLY_PUBLIC_KEY}'], $this->laravel->base_path('.env'));
    }
    /**
     * Inject Echo configuration into the application's main file.
     *
     * @return void
     */
    protected function inject_framework_specific_configuration()
    {
        if ($this->app_uses_vue()) {
            $import_path = $this->framework_packages['vue'];
            $file_paths = [$this->laravel->resource_path('js/app.ts'), $this->laravel->resource_path('js/app.js')];
        } else {
            $import_path = $this->framework_packages['react'];
            $file_paths = [$this->laravel->resource_path('js/app.tsx'), $this->laravel->resource_path('js/app.jsx')];
        }
        $file_path = array_filter($file_paths, file_exists(...))[0] ?? null;
        if (!$file_path) {
            $this->components->warn("Could not find file [{$file_paths[0]}]. Skipping automatic Echo configuration.");
            return;
        }
        $contents = file_get_contents($file_path);
        $echo_code = <<<JS
        import { configureEcho } from '{$import_path}';
        
        configureEcho({
            broadcaster: '{$this->driver}',
        });
        JS;
        preg_match_all('/^import .+;$/m', $contents, $matches);
        if (empty($matches[0])) {
            // Add the Echo configuration to the top of the file if no import statements are found...
            $new_contents = $echo_code . PHP_EOL . $contents;
            file_put_contents($file_path, $new_contents);
        } else {
            // Add Echo configuration after the last import...
            $last_import = array_last($matches[0]);
            $position_of_last_import = strrpos($contents, $last_import);
            if ($position_of_last_import !== false) {
                $insert_position = $position_of_last_import + strlen($last_import);
                $new_contents = substr($contents, 0, $insert_position) . PHP_EOL . $echo_code . substr($contents, $insert_position);
                file_put_contents($file_path, $new_contents);
            }
        }
        $this->components->info('Echo configuration added to [' . basename($file_path) . '].');
    }
    /**
     * Install Laravel Reverb into the application if desired.
     *
     * @return void
     */
    protected function install_reverb()
    {
        if ($this->driver !== 'reverb' || $this->option('without-reverb') || Installed_Versions::is_installed('laravel/reverb')) {
            return;
        }
        if (!confirm('Would you like to install Laravel Reverb?', default: true)) {
            return;
        }
        $this->require_composer_packages($this->option('composer'), ['laravel/reverb:^1.0']);
        Process::run([php_binary(), artisan_binary(), 'reverb:install']);
        $this->components->info('Reverb installed successfully.');
    }
    /**
     * Install and build Node dependencies.
     *
     * @return void
     */
    protected function install_node_dependencies()
    {
        if ($this->option('without-node') || !confirm('Would you like to install and build the Node dependencies required for broadcasting?', default: true)) {
            return;
        }
        $this->components->info('Installing and building Node dependencies.');
        if (file_exists(base_path('pnpm-lock.yaml'))) {
            $commands = ['pnpm add --save-dev laravel-echo pusher-js', 'pnpm run build'];
        } elseif (file_exists(base_path('yarn.lock'))) {
            $commands = ['yarn add --dev laravel-echo pusher-js', 'yarn run build'];
        } elseif (file_exists(base_path('bun.lock')) || file_exists(base_path('bun.lockb'))) {
            $commands = ['bun add --dev laravel-echo pusher-js', 'bun run build'];
        } else {
            $commands = ['npm install --save-dev laravel-echo pusher-js', 'npm run build'];
        }
        if ($this->app_uses_vue()) {
            $commands[0] .= ' ' . $this->framework_packages['vue'];
        } elseif ($this->app_uses_react()) {
            $commands[0] .= ' ' . $this->framework_packages['react'];
        }
        $command = Process::command(implode(' && ', $commands))->path(base_path());
        if (!windows_os()) {
            $command->tty(true);
        }
        if ($command->run()->failed()) {
            $this->components->warn("Node dependency installation failed. Please run the following commands manually: \n\n" . implode(' && ', $commands));
        } else {
            $this->components->info('Node dependencies installed successfully.');
        }
    }
    /**
     * Resolve the provider to use based on the user's choice.
     */
    protected function resolve_driver(): string
    {
        if ($this->option('reverb')) {
            return 'reverb';
        }
        if ($this->option('pusher')) {
            return 'pusher';
        }
        if ($this->option('ably')) {
            return 'ably';
        }
        return select('Which broadcasting driver would you like to use?', ['reverb' => 'Laravel Reverb', 'pusher' => 'Pusher', 'ably' => 'Ably']);
    }
    /**
     * Detect if the user is using a supported framework (React or Vue).
     */
    protected function is_using_supported_framework(): bool
    {
        if ($this->app_uses_react()) {
            return true;
        }
        return $this->app_uses_vue();
    }
    /**
     * Detect if the user is using React.
     */
    protected function app_uses_react(): bool
    {
        return $this->package_dependencies_include('react');
    }
    /**
     * Detect if the user is using Vue.
     */
    protected function app_uses_vue(): bool
    {
        return $this->package_dependencies_include('vue');
    }
    /**
     * Detect if the package is installed.
     */
    protected function package_dependencies_include(string $package): bool
    {
        $package_json_path = $this->laravel->base_path('package.json');
        if (!file_exists($package_json_path)) {
            return false;
        }
        $package_json = json_decode(file_get_contents($package_json_path), true);
        return isset($package_json['dependencies'][$package]) || isset($package_json['devDependencies'][$package]);
    }
}