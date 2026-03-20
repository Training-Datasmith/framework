<?php

declare (strict_types=1);
namespace Illuminate\Foundation;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
class Package_Manifest
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    public $files;
    /**
     * The vendor path.
     *
     * @var string
     */
    public $vendor_path;
    /**
     * The loaded manifest array.
     *
     * @var array
     */
    public $manifest;
    /**
     * Create a new package manifest instance.
     *
     * @param  string  $basePath
     * @param  string  $manifestPath
     */
    public function __construct(
        Filesystem $files,
        /**
         * The base path.
         */
        public $base_path,
        /**
         * The manifest path.
         */
        public $manifest_path
    )
    {
        $this->files = $files;
        $this->vendor_path = Env::get('COMPOSER_VENDOR_DIR') ?: $this->base_path . '/vendor';
    }
    /**
     * Get all of the service provider class names for all packages.
     *
     * @return array
     */
    public function providers()
    {
        return $this->config('providers');
    }
    /**
     * Get all of the aliases for all packages.
     *
     * @return array
     */
    public function aliases()
    {
        return $this->config('aliases');
    }
    /**
     * Get all of the values for all packages for the given configuration name.
     *
     * @param  string  $key
     * @return array
     */
    public function config($key)
    {
        return (new Collection($this->get_manifest()))->flat_map(fn($configuration): array => (array) ($configuration[$key] ?? []))->filter()->all();
    }
    /**
     * Get the current package manifest.
     *
     * @return array
     */
    protected function get_manifest()
    {
        if (!is_null($this->manifest)) {
            return $this->manifest;
        }
        if (!is_file($this->manifest_path)) {
            $this->build();
        }
        return $this->manifest = is_file($this->manifest_path) ? $this->files->get_require($this->manifest_path) : [];
    }
    /**
     * Build the manifest and write it to disk.
     */
    public function build(): void
    {
        $packages = [];
        if ($this->files->exists($path = $this->vendor_path . '/composer/installed.json')) {
            $installed = json_decode($this->files->get($path), true);
            $packages = $installed['packages'] ?? $installed;
        }
        $ignore_all = in_array('*', $ignore = $this->packages_to_ignore());
        $this->write((new Collection($packages))->map_with_keys(fn($package): array => [$this->format($package['name']) => $package['extra']['laravel'] ?? []])->each(function (array $configuration) use (&$ignore): void {
            $ignore = array_merge($ignore, $configuration['dont-discover'] ?? []);
        })->reject(fn($configuration, $package): bool => $ignore_all || in_array($package, $ignore))->filter()->all());
    }
    /**
     * Format the given package name.
     *
     * @param  string  $package
     */
    protected function format($package): string
    {
        return str_replace($this->vendor_path . '/', '', $package);
    }
    /**
     * Get all of the package names that should be ignored.
     *
     * @return array
     */
    protected function packages_to_ignore()
    {
        if (!is_file($this->base_path . '/composer.json')) {
            return [];
        }
        return json_decode(file_get_contents($this->base_path . '/composer.json'), true)['extra']['laravel']['dont-discover'] ?? [];
    }
    /**
     * Write the given manifest array to disk.
     *
     * @return void
     * @throws \Exception
     */
    protected function write(array $manifest)
    {
        if (!is_writable($dirname = dirname((string) $this->manifest_path))) {
            throw new Exception("The {$dirname} directory must be present and writable.");
        }
        $this->files->replace($this->manifest_path, '<?php return ' . var_export($manifest, true) . ';');
    }
}