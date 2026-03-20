<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bootstrap;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Spl_File_Info;
use Symfony\Component\Finder\Finder;
class Load_Configuration
{
    /**
     * The closure that resolves the permanent, static configuration if applicable.
     *
     * @var (Closure(Application): array<array-key, mixed>)|null
     */
    protected static ?Closure $always_use_config = null;
    /**
     * Bootstrap the given application.
     */
    public function bootstrap(Application $app): void
    {
        $items = [];
        // First we will see if we have a cache configuration file. If we do, we'll load
        // the configuration items from that file so that it is very quick. Otherwise
        // we will need to spin through every configuration file and load them all.
        $loaded_from_cache = false;
        if (self::$always_use_config !== null) {
            $items = $app->call(self::$always_use_config);
            $loaded_from_cache = true;
        } elseif (file_exists($cached = $app->get_cached_config_path())) {
            $items = require $cached;
            $loaded_from_cache = true;
        }
        $app->instance('config_loaded_from_cache', $loaded_from_cache);
        // Next we will spin through all of the configuration files in the configuration
        // directory and load each one into the repository. This will make all of the
        // options available to the developer for use in various parts of this app.
        $app->instance('config', $config = new Repository($items));
        if (!$loaded_from_cache) {
            $this->load_configuration_files($app, $config);
        }
        // Finally, we will set the application's environment based on the configuration
        // values that were loaded. We will pass a callback which will be used to get
        // the environment in a web context where an "--env" switch is not present.
        $app->detect_environment(fn() => $config->get('app.env', 'production'));
        $app->resolve_environment_using($app->environment(...));
        date_default_timezone_set($config->get('app.timezone', 'UTC'));
        mb_internal_encoding('UTF-8');
    }
    /**
     * Load the configuration items from all of the files.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function load_configuration_files(Application $app, Repository_Contract $repository)
    {
        $files = $this->get_configuration_files($app);
        $should_merge = method_exists($app, 'shouldMergeFrameworkConfiguration') ? $app->should_merge_framework_configuration() : true;
        $base = $should_merge ? $this->get_base_configuration() : [];
        foreach ((new Collection($base))->diff_keys($files) as $name => $config) {
            $repository->set($name, $config);
        }
        foreach ($files as $name => $path) {
            $base = $this->load_configuration_file($repository, $name, $path, $base);
        }
        foreach ($base as $name => $config) {
            $repository->set($name, $config);
        }
    }
    /**
     * Load the given configuration file.
     *
     * @param  string  $name
     * @param  string  $path
     */
    protected function load_configuration_file(Repository_Contract $repository, $name, $path, array $base): array
    {
        $config = (fn() => require $path)();
        if (isset($base[$name])) {
            $config = array_merge($base[$name], $config);
            foreach ($this->mergeable_options($name) as $option) {
                if (isset($config[$option])) {
                    $config[$option] = array_merge($base[$name][$option], $config[$option]);
                }
            }
            unset($base[$name]);
        }
        $repository->set($name, $config);
        return $base;
    }
    /**
     * Get the options within the configuration file that should be merged again.
     */
    protected function mergeable_options(string $name): array
    {
        return ['auth' => ['guards', 'providers', 'passwords'], 'broadcasting' => ['connections'], 'cache' => ['stores'], 'database' => ['connections'], 'filesystems' => ['disks'], 'logging' => ['channels'], 'mail' => ['mailers'], 'queue' => ['connections']][$name] ?? [];
    }
    /**
     * Get all of the configuration files for the application.
     */
    protected function get_configuration_files(Application $app): array
    {
        $files = [];
        $config_path = realpath($app->config_path());
        if (!$config_path) {
            return [];
        }
        foreach (Finder::create()->files()->name('*.php')->in($config_path) as $file) {
            $directory = $this->get_nested_directory($file, $config_path);
            $files[$directory . basename($file->get_real_path(), '.php')] = $file->get_real_path();
        }
        ksort($files, SORT_NATURAL);
        return $files;
    }
    /**
     * Get the configuration file nesting path.
     *
     * @param  string  $configPath
     * @return string
     */
    protected function get_nested_directory(Spl_File_Info $file, $config_path)
    {
        $directory = $file->get_path();
        if ($nested = trim(str_replace($config_path, '', $directory), DIRECTORY_SEPARATOR)) {
            return str_replace(DIRECTORY_SEPARATOR, '.', $nested) . '.';
        }
        return $nested;
    }
    /**
     * Get the base configuration files.
     */
    protected function get_base_configuration(): array
    {
        $config = [];
        foreach (Finder::create()->files()->name('*.php')->in(__DIR__ . '/../../../../config') as $file) {
            $config[basename($file->get_real_path(), '.php')] = require $file->get_real_path();
        }
        return $config;
    }
    /**
     * Set a callback to return the permanent, static configuration values.
     *
     * @param  (Closure(Application): array<array-key, mixed>)|null  $alwaysUseConfig
     */
    public static function always_use(?Closure $always_use_config): void
    {
        static::$always_use_config = $always_use_config;
    }
}