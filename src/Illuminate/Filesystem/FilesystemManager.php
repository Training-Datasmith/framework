<?php

declare (strict_types=1);
namespace Illuminate\Filesystem;

use Aws\S3\S3Client;
use Closure;
use Illuminate\Contracts\Filesystem\Factory as FactoryContract;
use Illuminate\Support\Arr;
use function Illuminate\Support\enum_value;
use InvalidArgumentException;
use League\Flysystem\Aws_S3v3\Aws_S3v3adapter as S3Adapter;
use League\Flysystem\Aws_S3v3\Portable_Visibility_Converter as AwsS3PortableVisibilityConverter;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Filesystem_Adapter as FlysystemAdapter;
use League\Flysystem\Ftp\Ftp_Adapter;
use League\Flysystem\Ftp\Ftp_Connection_Options;
use League\Flysystem\Local\Local_Filesystem_Adapter as LocalAdapter;
use League\Flysystem\Path_Prefixing\Path_Prefixed_Adapter;
use League\Flysystem\Phpseclib_V3\Sftp_Adapter;
use League\Flysystem\Phpseclib_V3\Sftp_Connection_Provider;
use League\Flysystem\Read_Only\Read_Only_Filesystem_Adapter;
use League\Flysystem\Unix_Visibility\Portable_Visibility_Converter;
use League\Flysystem\Visibility;
/**
 * @mixin \Illuminate\Contracts\Filesystem\Filesystem
 * @mixin \Illuminate\Filesystem\FilesystemAdapter
 */
class Filesystem_Manager implements Factory_Contract
{
    /**
     * The array of resolved filesystem drivers.
     *
     * @var array
     */
    protected $disks = [];
    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $custom_creators = [];
    /**
     * Create a new filesystem manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function __construct(
        /**
         * The application instance.
         */
        protected $app
    )
    {
    }
    /**
     * Get a filesystem instance.
     *
     * @param  string|null  $name
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function drive($name = null)
    {
        return $this->disk($name);
    }
    /**
     * Get a filesystem instance.
     *
     * @param  \UnitEnum|string|null  $name
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function disk($name = null)
    {
        $name = enum_value($name) ?: $this->get_default_driver();
        return $this->disks[$name] = $this->get($name);
    }
    /**
     * Get a default cloud filesystem instance.
     *
     * @return \Illuminate\Contracts\Filesystem\Cloud
     */
    public function cloud()
    {
        $name = $this->get_default_cloud_driver();
        return $this->disks[$name] = $this->get($name);
    }
    /**
     * Build an on-demand disk.
     *
     * @param  string|array  $config
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function build($config)
    {
        return $this->resolve('ondemand', is_array($config) ? $config : ['driver' => 'local', 'root' => $config]);
    }
    /**
     * Attempt to get the disk from the local cache.
     *
     * @param  string  $name
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected function get($name)
    {
        return $this->disks[$name] ?? $this->resolve($name);
    }
    /**
     * Resolve the given disk.
     *
     * @param  string  $name
     * @param  array|null  $config
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name, $config = null)
    {
        $config ??= $this->get_config($name);
        if (empty($config['driver'])) {
            throw new InvalidArgumentException("Disk [{$name}] does not have a configured driver.");
        }
        $driver = $config['driver'];
        if (isset($this->custom_creators[$driver])) {
            return $this->call_custom_creator($config);
        }
        $driver_method = 'create' . ucfirst((string) $driver) . 'Driver';
        if (!method_exists($this, $driver_method)) {
            throw new InvalidArgumentException("Driver [{$driver}] is not supported.");
        }
        return $this->{$driver_method}($config, $name);
    }
    /**
     * Call a custom driver creator.
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected function call_custom_creator(array $config)
    {
        return $this->custom_creators[$config['driver']]($this->app, $config);
    }
    /**
     * Create an instance of the local driver.
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function create_local_driver(array $config, string $name = 'local'): \Illuminate\Filesystem\Local_Filesystem_Adapter
    {
        $visibility = Portable_Visibility_Converter::from_array($config['permissions'] ?? [], $config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE);
        $links = ($config['links'] ?? null) === 'skip' ? Local_Adapter::SKIP_LINKS : Local_Adapter::DISALLOW_LINKS;
        $adapter = new Local_Adapter($config['root'], $visibility, $config['lock'] ?? LOCK_EX, $links);
        return (new Local_Filesystem_Adapter($this->create_flysystem($adapter, $config), $adapter, $config))->disk_name($name)->should_serve_signed_urls($config['serve'] ?? false, fn() => $this->app['url']);
    }
    /**
     * Create an instance of the ftp driver.
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function create_ftp_driver(array $config): \Illuminate\Filesystem\Filesystem_Adapter
    {
        if (!isset($config['root'])) {
            $config['root'] = '';
        }
        $adapter = new Ftp_Adapter(Ftp_Connection_Options::from_array($config));
        return new Filesystem_Adapter($this->create_flysystem($adapter, $config), $adapter, $config);
    }
    /**
     * Create an instance of the sftp driver.
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function create_sftp_driver(array $config): \Illuminate\Filesystem\Filesystem_Adapter
    {
        $provider = Sftp_Connection_Provider::from_array($config);
        $root = $config['root'] ?? '';
        $visibility = Portable_Visibility_Converter::from_array($config['permissions'] ?? []);
        $adapter = new Sftp_Adapter($provider, $root, $visibility);
        return new Filesystem_Adapter($this->create_flysystem($adapter, $config), $adapter, $config);
    }
    /**
     * Create an instance of the Amazon S3 driver.
     *
     * @return \Illuminate\Contracts\Filesystem\Cloud
     */
    public function create_s3driver(array $config): \Illuminate\Filesystem\Aws_S3v3adapter
    {
        $s3Config = $this->format_s3config($config);
        $root = (string) ($s3Config['root'] ?? '');
        $visibility = new Aws_S3portable_Visibility_Converter($config['visibility'] ?? Visibility::PUBLIC);
        $stream_reads = $s3Config['stream_reads'] ?? false;
        $client = new S3Client($s3Config);
        $adapter = new S3Adapter($client, $s3Config['bucket'], $root, $visibility, null, $config['options'] ?? [], $stream_reads);
        return new Aws_S3v3adapter($this->create_flysystem($adapter, $config), $adapter, $s3Config, $client);
    }
    /**
     * Format the given S3 configuration with the default options.
     */
    protected function format_s3config(array $config): array
    {
        $config += ['version' => 'latest'];
        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret']);
            if (!empty($config['token'])) {
                $config['credentials']['token'] = $config['token'];
            }
        }
        return Arr::except($config, ['token']);
    }
    /**
     * Create a scoped driver.
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     * @throws \InvalidArgumentException
     */
    public function create_scoped_driver(array $config)
    {
        if (empty($config['disk'])) {
            throw new InvalidArgumentException('Scoped disk is missing "disk" configuration option.');
        }
        if (empty($config['prefix'])) {
            throw new InvalidArgumentException('Scoped disk is missing "prefix" configuration option.');
        }
        return $this->build(tap(is_string($config['disk']) ? $this->get_config($config['disk']) : $config['disk'], function (array &$parent) use ($config): void {
            if (empty($parent['prefix'])) {
                $parent['prefix'] = $config['prefix'];
            } else {
                $separator = $parent['directory_separator'] ?? DIRECTORY_SEPARATOR;
                $parent_prefix = rtrim((string) $parent['prefix'], $separator);
                $scoped_prefix = ltrim((string) $config['prefix'], $separator);
                $parent['prefix'] = "{$parent_prefix}{$separator}{$scoped_prefix}";
            }
            if (isset($config['visibility'])) {
                $parent['visibility'] = $config['visibility'];
            }
            if (isset($config['throw'])) {
                $parent['throw'] = $config['throw'];
            }
        }));
    }
    /**
     * Create a Flysystem instance with the given adapter.
     *
     * @return \League\Flysystem\FilesystemOperator
     */
    protected function create_flysystem(Flysystem_Adapter $adapter, array $config)
    {
        if ($config['read-only'] ?? false) {
            $adapter = new Read_Only_Filesystem_Adapter($adapter);
        }
        if (!empty($config['prefix'])) {
            $adapter = new Path_Prefixed_Adapter($adapter, $config['prefix']);
        }
        if (str_contains($config['endpoint'] ?? '', 'r2.cloudflarestorage.com')) {
            $config['retain_visibility'] = false;
        }
        return new Flysystem($adapter, Arr::only($config, ['directory_visibility', 'disable_asserts', 'retain_visibility', 'temporary_url', 'url', 'visibility']));
    }
    /**
     * Set the given disk instance.
     *
     * @param  string  $name
     * @param  mixed  $disk
     * @return $this
     */
    public function set($name, $disk): static
    {
        $this->disks[$name] = $disk;
        return $this;
    }
    /**
     * Get the filesystem connection configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function get_config($name)
    {
        return $this->app['config']["filesystems.disks.{$name}"] ?: [];
    }
    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function get_default_driver()
    {
        return $this->app['config']['filesystems.default'];
    }
    /**
     * Get the default cloud driver name.
     *
     * @return string
     */
    public function get_default_cloud_driver()
    {
        return $this->app['config']['filesystems.cloud'] ?? 's3';
    }
    /**
     * Unset the given disk instances.
     *
     * @param  array|string  $disk
     * @return $this
     */
    public function forget_disk($disk): static
    {
        foreach ((array) $disk as $disk_name) {
            unset($this->disks[$disk_name]);
        }
        return $this;
    }
    /**
     * Disconnect the given disk and remove from local cache.
     *
     * @param  string|null  $name
     */
    public function purge($name = null): void
    {
        $name ??= $this->get_default_driver();
        unset($this->disks[$name]);
    }
    /**
     * Register a custom driver creator Closure.
     *
     * @param  string  $driver
     * @return $this
     */
    public function extend($driver, Closure $callback): static
    {
        $this->custom_creators[$driver] = $callback;
        return $this;
    }
    /**
     * Set the application instance used by the manager.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return $this
     */
    public function set_application($app): static
    {
        $this->app = $app;
        return $this;
    }
    /**
     * Dynamically call the default driver instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->disk()->{$method}(...$parameters);
    }
}