<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use function Laravel\Prompts\select;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Finder\Finder;
#[As_Command(name: 'config:publish')]
class Config_Publish_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'config:publish
                    {name? : The name of the configuration file to publish}
                    {--all : Publish all configuration files}
                    {--force : Overwrite any existing configuration files}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish configuration files to your application';
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $config = $this->get_base_configuration_files();
        if (is_null($this->argument('name')) && $this->option('all')) {
            foreach ($config as $key => $file) {
                $this->publish($key, $file, $this->laravel->config_path() . '/' . $key . '.php');
            }
            return;
        }
        $name = (string) (is_null($this->argument('name')) ? select(label: 'Which configuration file would you like to publish?', options: (new Collection($config))->map(fn(string $path): string => basename($path, '.php'))) : $this->argument('name'));
        if (!isset($config[$name])) {
            $this->components->error('Unrecognized configuration file.');
            return 1;
        }
        $this->publish($name, $config[$name], $this->laravel->config_path() . '/' . $name . '.php');
    }
    /**
     * Publish the given file to the given destination.
     *
     * @return void
     */
    protected function publish(string $name, string $file, string $destination)
    {
        if (file_exists($destination) && !$this->option('force')) {
            $this->components->error("The '{$name}' configuration file already exists.");
            return;
        }
        copy($file, $destination);
        $this->components->info("Published '{$name}' configuration file.");
    }
    /**
     * Get an array containing the base configuration files.
     *
     * @return array
     */
    protected function get_base_configuration_files()
    {
        $config = [];
        $should_merge_configuration = $this->laravel->should_merge_framework_configuration();
        foreach (Finder::create()->files()->name('*.php')->in(__DIR__ . '/../../../../config') as $file) {
            $name = basename($file->get_real_path(), '.php');
            $config[$name] = $should_merge_configuration === true && file_exists($stub_path = __DIR__ . '/../../../../config-stubs/' . $name . '.php') ? $stub_path : $file->get_real_path();
        }
        return (new Collection($config))->sort_keys()->all();
    }
}