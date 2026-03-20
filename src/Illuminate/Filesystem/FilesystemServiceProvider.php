<?php

declare (strict_types=1);
namespace Illuminate\Filesystem;

use Illuminate\Contracts\Foundation\Caches_Routes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Service_Provider;
class Filesystem_Service_Provider extends Service_Provider
{
    /**
     * Bootstrap the filesystem.
     */
    public function boot(): void
    {
        $this->serve_files();
    }
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->register_native_filesystem();
        $this->register_flysystem();
    }
    /**
     * Register the native filesystem implementation.
     *
     * @return void
     */
    protected function register_native_filesystem()
    {
        $this->app->singleton('files', fn(): \Illuminate\Filesystem\Filesystem => new Filesystem());
    }
    /**
     * Register the driver based filesystem.
     *
     * @return void
     */
    protected function register_flysystem()
    {
        $this->register_manager();
        $this->app->singleton('filesystem.disk', fn($app) => $app['filesystem']->disk($this->get_default_driver()));
        $this->app->singleton('filesystem.cloud', fn($app) => $app['filesystem']->disk($this->get_cloud_driver()));
    }
    /**
     * Register the filesystem manager.
     *
     * @return void
     */
    protected function register_manager()
    {
        $this->app->singleton('filesystem', fn($app): \Illuminate\Filesystem\Filesystem_Manager => new Filesystem_Manager($app));
    }
    /**
     * Register protected file serving.
     *
     * @return void
     */
    protected function serve_files()
    {
        if ($this->app instanceof Caches_Routes && $this->app->routes_are_cached()) {
            return;
        }
        foreach ($this->app['config']['filesystems.disks'] ?? [] as $disk => $config) {
            if (!$this->should_serve_files($config)) {
                continue;
            }
            $this->app->booted(function ($app) use ($disk, $config): void {
                $uri = isset($config['url']) ? rtrim(parse_url((string) $config['url'])['path'], '/') : '/storage';
                $is_production = $app->is_production();
                Route::get($uri . '/{path}', fn(Request $request, string $path) => (new Serve_File($disk, $config, $is_production))($request, $path))->where('path', '.*')->name('storage.' . $disk);
                Route::put($uri . '/{path}', fn(Request $request, string $path): \Illuminate\Http\Response => (new Receive_File($disk, $config, $is_production))($request, $path))->where('path', '.*')->name('storage.' . $disk . '.upload');
            });
        }
    }
    /**
     * Determine if the disk is serveable.
     */
    protected function should_serve_files(array $config): bool
    {
        return $config['driver'] === 'local' && ($config['serve'] ?? false);
    }
    /**
     * Get the default file driver.
     *
     * @return string
     */
    protected function get_default_driver()
    {
        return $this->app['config']['filesystems.default'];
    }
    /**
     * Get the default cloud based file driver.
     *
     * @return string
     */
    protected function get_cloud_driver()
    {
        return $this->app['config']['filesystems.cloud'];
    }
}