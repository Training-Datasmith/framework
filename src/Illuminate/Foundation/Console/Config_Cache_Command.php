<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use LogicException;
use Symfony\Component\Console\Attribute\As_Command;
use Throwable;
#[As_Command(name: 'config:cache')]
class Config_Cache_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'config:cache';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a cache file for faster configuration loading';
    /**
     * Create a new config cache command instance.
     */
    public function __construct(
        /**
         * The filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     *
     *
     * @throws \LogicException
     */
    public function handle(): void
    {
        $this->call_silent('config:clear');
        $config = $this->get_fresh_configuration();
        $config_path = $this->laravel->get_cached_config_path();
        $this->files->put($config_path, '<?php return ' . var_export($config, true) . ';' . PHP_EOL);
        try {
            require $config_path;
        } catch (Throwable $e) {
            $this->files->delete($config_path);
            foreach (Arr::dot($config) as $key => $value) {
                try {
                    eval(var_export($value, true) . ';');
                } catch (Throwable $e) {
                    throw new LogicException("Your configuration files could not be serialized because the value at \"{$key}\" is non-serializable.", 0, $e);
                }
            }
            throw new LogicException('Your configuration files are not serializable.', 0, $e);
        }
        $this->components->info('Configuration cached successfully.');
    }
    /**
     * Boot a fresh copy of the application configuration.
     *
     * @return array
     */
    protected function get_fresh_configuration()
    {
        $app = require $this->laravel->bootstrap_path('app.php');
        $app->use_storage_path($this->laravel->storage_path());
        $app->make(Console_Kernel_Contract::class)->bootstrap();
        return $app['config']->all();
    }
}