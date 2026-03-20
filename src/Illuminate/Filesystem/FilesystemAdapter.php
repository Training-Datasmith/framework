<?php

declare (strict_types=1);
namespace Illuminate\Filesystem;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\Exception_Handler;
use Illuminate\Contracts\Filesystem\Cloud as CloudFilesystemContract;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Http\Uploaded_File;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use League\Flysystem\Filesystem_Operator;
use League\Flysystem\Ftp\Ftp_Adapter;
use League\Flysystem\Local\Local_Filesystem_Adapter as LocalAdapter;
use League\Flysystem\Path_Prefixer;
use League\Flysystem\Phpseclib_V3\Sftp_Adapter;
use League\Flysystem\Storage_Attributes;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Provide_Checksum;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Visibility;
use Php_Unit\Framework\Assert as PHPUnit;
use Psr\Http\Message\Stream_Interface;
use RuntimeException;
use Symfony\Component\Http_Foundation\Streamed_Response;
/**
 * @mixin \League\Flysystem\FilesystemOperator
 */
class Filesystem_Adapter implements Cloud_Filesystem_Contract
{
    use Conditionable;
    use Macroable {
        __call as macroCall;
    }
    /**
     * The Flysystem filesystem implementation.
     *
     * @var \League\Flysystem\FilesystemOperator
     */
    protected $driver;
    /**
     * The Flysystem PathPrefixer instance.
     *
     * @var \League\Flysystem\PathPrefixer
     */
    protected $prefixer;
    /**
     * The file server callback.
     *
     * @var \Closure|null
     */
    protected $serve_callback;
    /**
     * The temporary URL builder callback.
     *
     * @var \Closure|null
     */
    protected $temporary_url_callback;
    /**
     * The temporary upload URL builder callback.
     *
     * @var \Closure|null
     */
    protected $temporary_upload_url_callback;
    /**
     * Create a new filesystem adapter instance.
     */
    public function __construct(
        Filesystem_Operator $driver,
        /**
         * The Flysystem adapter implementation.
         */
        protected \League\Flysystem\Filesystem_Adapter $adapter,
        /**
         * The filesystem configuration.
         */
        protected array $config = []
    )
    {
        $this->driver = $driver;
        $separator = $this->config['directory_separator'] ?? DIRECTORY_SEPARATOR;
        $this->prefixer = new Path_Prefixer($this->config['root'] ?? '', $separator);
        if (isset($this->config['prefix'])) {
            $this->prefixer = new Path_Prefixer($this->prefixer->prefix_path($this->config['prefix']), $separator);
        }
    }
    /**
     * Assert that the given file or directory exists.
     *
     * @param  string|array  $path
     * @param  string|null  $content
     * @return $this
     */
    public function assert_exists($path, $content = null): static
    {
        clearstatcache();
        $paths = Arr::wrap($path);
        foreach ($paths as $path) {
            Php_Unit::assert_true($this->exists($path), "Unable to find a file or directory at path [{$path}].");
            if (!is_null($content)) {
                $actual = $this->get($path);
                Php_Unit::assert_same($content, $actual, "File or directory [{$path}] was found, but content [{$actual}] does not match [{$content}].");
            }
        }
        return $this;
    }
    /**
     * Assert that the number of files in path equals the expected count.
     *
     * @param  string  $path
     * @param  int  $count
     * @param  bool  $recursive
     * @return $this
     */
    public function assert_count($path, $count, $recursive = false): static
    {
        clearstatcache();
        $actual = count($this->files($path, $recursive));
        Php_Unit::assert_equals($count, $actual, "Expected [{$count}] files at [{$path}], but found [{$actual}].");
        return $this;
    }
    /**
     * Assert that the given file or directory does not exist.
     *
     * @param  string|array  $path
     * @return $this
     */
    public function assert_missing($path): static
    {
        clearstatcache();
        $paths = Arr::wrap($path);
        foreach ($paths as $path) {
            Php_Unit::assert_false($this->exists($path), "Found unexpected file or directory at path [{$path}].");
        }
        return $this;
    }
    /**
     * Assert that the given directory is empty.
     *
     * @param  string  $path
     * @return $this
     */
    public function assert_directory_empty($path): static
    {
        Php_Unit::assert_empty($this->all_files($path), "Directory [{$path}] is not empty.");
        return $this;
    }
    /**
     * Determine if a file or directory exists.
     *
     * @param  string  $path
     * @return bool
     */
    public function exists($path)
    {
        return $this->driver->has($path);
    }
    /**
     * Determine if a file or directory is missing.
     *
     * @param  string  $path
     */
    public function missing($path): bool
    {
        return !$this->exists($path);
    }
    /**
     * Determine if a file exists.
     *
     * @param  string  $path
     * @return bool
     */
    public function file_exists($path)
    {
        return $this->driver->file_exists($path);
    }
    /**
     * Determine if a file is missing.
     *
     * @param  string  $path
     */
    public function file_missing($path): bool
    {
        return !$this->file_exists($path);
    }
    /**
     * Determine if a directory exists.
     *
     * @param  string  $path
     * @return bool
     */
    public function directory_exists($path)
    {
        return $this->driver->directory_exists($path);
    }
    /**
     * Determine if a directory is missing.
     *
     * @param  string  $path
     */
    public function directory_missing($path): bool
    {
        return !$this->directory_exists($path);
    }
    /**
     * Get the full path to the file that exists at the given relative path.
     *
     * @param  string  $path
     * @return string
     */
    public function path($path)
    {
        return $this->prefixer->prefix_path($path);
    }
    /**
     * Get the contents of a file.
     *
     * @param  string  $path
     * @return string|null
     */
    public function get($path)
    {
        try {
            return $this->driver->read($path);
        } catch (Unable_To_Read_File $e) {
            throw_if($this->throws_exceptions(), $e);
            $this->report($e);
        }
    }
    /**
     * Get the contents of a file as decoded JSON.
     *
     * @param  string  $path
     * @param  int  $flags
     * @return array|null
     */
    public function json($path, $flags = 0)
    {
        $content = $this->get($path);
        return is_null($content) ? null : json_decode($content, true, 512, $flags);
    }
    /**
     * Create a streamed response for a given file.
     *
     * @param  string  $path
     * @param  string|null  $name
     * @param  string|null  $disposition
     */
    public function response($path, $name = null, array $headers = [], $disposition = 'inline'): \Symfony\Component\Http_Foundation\Streamed_Response
    {
        $response = new Streamed_Response();
        $headers['Content-Type'] ??= $this->mime_type($path);
        $headers['Content-Length'] ??= $this->size($path);
        if (!array_key_exists('Content-Disposition', $headers)) {
            $filename = $name ?? basename($path);
            $disposition = $response->headers->make_disposition($disposition, $filename, $this->fallback_name($filename));
            $headers['Content-Disposition'] = $disposition;
        }
        $response->headers->replace($headers);
        $response->set_callback(function () use ($path): void {
            $stream = $this->read_stream($path);
            fpassthru($stream);
            fclose($stream);
        });
        return $response;
    }
    /**
     * Create a streamed download response for a given file.
     *
     * @param  string  $path
     * @param  string|null  $name
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function serve(Request $request, $path, $name = null, array $headers = [])
    {
        return isset($this->serve_callback) ? call_user_func($this->serve_callback, $request, $path, $headers) : $this->response($path, $name, $headers);
    }
    /**
     * Create a streamed download response for a given file.
     *
     * @param  string  $path
     * @param  string|null  $name
     */
    public function download($path, $name = null, array $headers = []): \Symfony\Component\Http_Foundation\Streamed_Response
    {
        return $this->response($path, $name, $headers, 'attachment');
    }
    /**
     * Convert the string to ASCII characters that are equivalent to the given name.
     *
     * @param  string  $name
     */
    protected function fallback_name($name): string
    {
        return str_replace('%', '', Str::ascii($name));
    }
    /**
     * Write the contents of a file.
     *
     * @param  string  $path
     * @param  \Psr\Http\Message\StreamInterface|\Illuminate\Http\File|\Illuminate\Http\UploadedFile|string|resource  $contents
     * @param  mixed  $options
     * @return string|bool
     */
    public function put($path, $contents, $options = [])
    {
        $options = is_string($options) ? ['visibility' => $options] : (array) $options;
        // If the given contents is actually a file or uploaded file instance than we will
        // automatically store the file using a stream. This provides a convenient path
        // for the developer to store streams without managing them manually in code.
        if ($contents instanceof File || $contents instanceof Uploaded_File) {
            return $this->put_file($path, $contents, $options);
        }
        try {
            if ($contents instanceof Stream_Interface) {
                $this->driver->write_stream($path, $contents->detach(), $options);
                return true;
            }
            is_resource($contents) ? $this->driver->write_stream($path, $contents, $options) : $this->driver->write($path, $contents, $options);
        } catch (Unable_To_Write_File|Unable_To_Set_Visibility $e) {
            throw_if($this->throws_exceptions(), $e);
            $this->report($e);
            return false;
        }
        return true;
    }
    /**
     * Store the uploaded file on the disk.
     *
     * @param  \Illuminate\Http\File|\Illuminate\Http\UploadedFile|string  $path
     * @param  \Illuminate\Http\File|\Illuminate\Http\UploadedFile|string|array|null  $file
     * @param  mixed  $options
     * @return string|false
     */
    public function put_file($path, $file = null, $options = []): string|false
    {
        if (is_null($file) || is_array($file)) {
            [$path, $file, $options] = ['', $path, $file ?? []];
        }
        $file = is_string($file) ? new File($file) : $file;
        return $this->put_file_as($path, $file, $file->hash_name(), $options);
    }
    /**
     * Store the uploaded file on the disk with a given name.
     *
     * @param  \Illuminate\Http\File|\Illuminate\Http\UploadedFile|string  $path
     * @param  \Illuminate\Http\File|\Illuminate\Http\UploadedFile|string|array|null  $file
     * @param  string|array|null  $name
     * @param  mixed  $options
     * @return string|false
     */
    public function put_file_as($path, $file, $name = null, $options = []): string|false
    {
        if (is_null($name) || is_array($name)) {
            [$path, $file, $name, $options] = ['', $path, $file, $name ?? []];
        }
        $stream = fopen(is_string($file) ? $file : $file->get_real_path(), 'r');
        // Next, we will format the path of the file and store the file using a stream since
        // they provide better performance than alternatives. Once we write the file this
        // stream will get closed automatically by us so the developer doesn't have to.
        $result = $this->put($path = trim($path . '/' . $name, '/'), $stream, $options);
        if (is_resource($stream)) {
            fclose($stream);
        }
        return $result ? $path : false;
    }
    /**
     * Get the visibility for the given path.
     *
     * @param  string  $path
     */
    public function get_visibility($path): string
    {
        if ($this->driver->visibility($path) == Visibility::PUBLIC) {
            return Filesystem_Contract::VISIBILITY_PUBLIC;
        }
        return Filesystem_Contract::VISIBILITY_PRIVATE;
    }
    /**
     * Set the visibility for the given path.
     *
     * @param  string  $path
     * @param  string  $visibility
     */
    public function set_visibility($path, $visibility): bool
    {
        try {
            $this->driver->set_visibility($path, $this->parse_visibility($visibility));
        } catch (Unable_To_Set_Visibility $e) {
            throw_if($this->throws_exceptions(), $e);
            $this->report($e);
            return false;
        }
        return true;
    }
    /**
     * Prepend to a file.
     *
     * @param  string  $path
     * @param  string  $data
     * @param  string  $separator
     * @return bool
     */
    public function prepend($path, $data, $separator = PHP_EOL)
    {
        if ($this->file_exists($path)) {
            return $this->put($path, $data . $separator . $this->get($path));
        }
        return $this->put($path, $data);
    }
    /**
     * Append to a file.
     *
     * @param  string  $path
     * @param  string  $data
     * @param  string  $separator
     * @return bool
     */
    public function append($path, $data, $separator = PHP_EOL)
    {
        if ($this->file_exists($path)) {
            return $this->put($path, $this->get($path) . $separator . $data);
        }
        return $this->put($path, $data);
    }
    /**
     * Delete the file at a given path.
     *
     * @param  string|array  $paths
     * @return bool
     */
    public function delete($paths)
    {
        $paths = is_array($paths) ? $paths : func_get_args();
        $success = true;
        foreach ($paths as $path) {
            try {
                $this->driver->delete($path);
            } catch (Unable_To_Delete_File $e) {
                throw_if($this->throws_exceptions(), $e);
                $this->report($e);
                $success = false;
            }
        }
        return $success;
    }
    /**
     * Copy a file to a new location.
     *
     * @param  string  $from
     * @param  string  $to
     */
    public function copy($from, $to): bool
    {
        try {
            $this->driver->copy($from, $to);
        } catch (Unable_To_Copy_File $e) {
            throw_if($this->throws_exceptions(), $e);
            $this->report($e);
            return false;
        }
        return true;
    }
    /**
     * Move a file to a new location.
     *
     * @param  string  $from
     * @param  string  $to
     */
    public function move($from, $to): bool
    {
        try {
            $this->driver->move($from, $to);
        } catch (Unable_To_Move_File $e) {
            throw_if($this->throws_exceptions(), $e);
            $this->report($e);
            return false;
        }
        return true;
    }
    /**
     * Get the file size of a given file.
     *
     * @param  string  $path
     * @return int
     */
    public function size($path)
    {
        return $this->driver->file_size($path);
    }
    /**
     * Get the checksum for a file.
     *
     * @return string|false
     *
     * @throws UnableToProvideChecksum
     */
    public function checksum(string $path, array $options = [])
    {
        try {
            return $this->driver->checksum($path, $options);
        } catch (Unable_To_Provide_Checksum $e) {
            throw_if($this->throws_exceptions(), $e);
            $this->report($e);
            return false;
        }
    }
    /**
     * Get the mime-type of a given file.
     *
     * @param  string  $path
     * @return string|false
     */
    public function mime_type($path)
    {
        try {
            return $this->driver->mime_type($path);
        } catch (Unable_To_Retrieve_Metadata $e) {
            throw_if($this->throws_exceptions(), $e);
            $this->report($e);
        }
        return false;
    }
    /**
     * Get the file's last modification time.
     *
     * @param  string  $path
     * @return int
     */
    public function last_modified($path)
    {
        return $this->driver->last_modified($path);
    }
    /**
     * {@inheritdoc}
     */
    public function read_stream($path)
    {
        try {
            return $this->driver->read_stream($path);
        } catch (Unable_To_Read_File $e) {
            throw_if($this->throws_exceptions(), $e);
            $this->report($e);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function write_stream($path, $resource, array $options = []): bool
    {
        try {
            $this->driver->write_stream($path, $resource, $options);
        } catch (Unable_To_Write_File|Unable_To_Set_Visibility $e) {
            throw_if($this->throws_exceptions(), $e);
            $this->report($e);
            return false;
        }
        return true;
    }
    /**
     * Get the URL for the file at the given path.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \RuntimeException
     */
    public function url($path)
    {
        if (isset($this->config['prefix'])) {
            $path = $this->concat_path_to_url($this->config['prefix'], $path);
        }
        $adapter = $this->adapter;
        if (method_exists($adapter, 'getUrl')) {
            return $adapter->get_url($path);
        }
        if (method_exists($this->driver, 'getUrl')) {
            return $this->driver->get_url($path);
        }
        if ($adapter instanceof Ftp_Adapter || $adapter instanceof Sftp_Adapter) {
            return $this->get_ftp_url($path);
        }
        if ($adapter instanceof Local_Adapter) {
            return $this->get_local_url($path);
        }
        throw new RuntimeException('This driver does not support retrieving URLs.');
    }
    /**
     * Get the URL for the file at the given path.
     *
     * @param  string  $path
     * @return string
     */
    protected function get_ftp_url($path)
    {
        return isset($this->config['url']) ? $this->concat_path_to_url($this->config['url'], $path) : $path;
    }
    /**
     * Get the URL for the file at the given path.
     *
     * @param  string  $path
     * @return string
     */
    protected function get_local_url($path)
    {
        // If an explicit base URL has been set on the disk configuration then we will use
        // it as the base URL instead of the default path. This allows the developer to
        // have full control over the base path for this filesystem's generated URLs.
        if (isset($this->config['url'])) {
            return $this->concat_path_to_url($this->config['url'], $path);
        }
        $path = '/storage/' . $path;
        // If the path contains "storage/public", it probably means the developer is using
        // the default disk to generate the path instead of the "public" disk like they
        // are really supposed to use. We will remove the public from this path here.
        if (str_contains($path, '/storage/public/')) {
            return Str::replace_first('/public/', '/', $path);
        }
        return $path;
    }
    /**
     * Determine if temporary URLs can be generated.
     */
    public function provides_temporary_urls(): bool
    {
        return method_exists($this->adapter, 'getTemporaryUrl') || isset($this->temporary_url_callback);
    }
    /**
     * Determine if temporary upload URLs can be generated.
     */
    public function provides_temporary_upload_urls(): bool
    {
        return method_exists($this->adapter, 'temporaryUploadUrl') || isset($this->temporary_upload_url_callback);
    }
    /**
     * Get a temporary URL for the file at the given path.
     *
     * @param  string  $path
     * @param  \DateTimeInterface  $expiration
     * @return string
     * @throws \RuntimeException
     */
    public function temporary_url($path, $expiration, array $options = [])
    {
        if (method_exists($this->adapter, 'getTemporaryUrl')) {
            return $this->adapter->get_temporary_url($path, $expiration, $options);
        }
        if ($this->temporary_url_callback) {
            return $this->temporary_url_callback->bind_to($this, static::class)($path, $expiration, $options);
        }
        throw new RuntimeException('This driver does not support creating temporary URLs.');
    }
    /**
     * Get a temporary upload URL for the file at the given path.
     *
     * @param  string  $path
     * @param  \DateTimeInterface  $expiration
     * @return array
     * @throws \RuntimeException
     */
    public function temporary_upload_url($path, $expiration, array $options = [])
    {
        if (method_exists($this->adapter, 'temporaryUploadUrl')) {
            return $this->adapter->temporary_upload_url($path, $expiration, $options);
        }
        if ($this->temporary_upload_url_callback) {
            return $this->temporary_upload_url_callback->bind_to($this, static::class)($path, $expiration, $options);
        }
        throw new RuntimeException('This driver does not support creating temporary upload URLs.');
    }
    /**
     * Concatenate a path to a URL.
     *
     * @param  string  $url
     * @param  string  $path
     */
    protected function concat_path_to_url($url, $path): string
    {
        return rtrim($url, '/') . '/' . ltrim($path, '/');
    }
    /**
     * Replace the scheme, host and port of the given UriInterface with values from the given URL.
     *
     * @param  \Psr\Http\Message\UriInterface  $uri
     * @param  string  $url
     * @return \Psr\Http\Message\UriInterface
     */
    protected function replace_base_url($uri, $url)
    {
        $parsed = parse_url($url);
        return $uri->with_scheme($parsed['scheme'])->with_host($parsed['host'])->with_port($parsed['port'] ?? null);
    }
    /**
     * Get an array of all files in a directory.
     *
     * @param  string|null  $directory
     * @param  bool  $recursive
     * @return array
     */
    public function files($directory = null, $recursive = false)
    {
        return $this->driver->list_contents($directory ?? '', $recursive)->filter(fn(Storage_Attributes $attributes) => $attributes->is_file())->sort_by_path()->map(fn(Storage_Attributes $attributes) => $attributes->path())->to_array();
    }
    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param  string|null  $directory
     * @return array
     */
    public function all_files($directory = null)
    {
        return $this->files($directory, true);
    }
    /**
     * Get all of the directories within a given directory.
     *
     * @param  string|null  $directory
     * @param  bool  $recursive
     * @return array
     */
    public function directories($directory = null, $recursive = false)
    {
        return $this->driver->list_contents($directory ?? '', $recursive)->filter(fn(Storage_Attributes $attributes) => $attributes->is_dir())->map(fn(Storage_Attributes $attributes) => $attributes->path())->to_array();
    }
    /**
     * Get all the directories within a given directory (recursive).
     *
     * @param  string|null  $directory
     * @return array
     */
    public function all_directories($directory = null)
    {
        return $this->directories($directory, true);
    }
    /**
     * Create a directory.
     *
     * @param  string  $path
     */
    public function make_directory($path): bool
    {
        try {
            $this->driver->create_directory($path);
        } catch (Unable_To_Create_Directory|Unable_To_Set_Visibility $e) {
            throw_if($this->throws_exceptions(), $e);
            $this->report($e);
            return false;
        }
        return true;
    }
    /**
     * Recursively delete a directory.
     *
     * @param  string  $directory
     */
    public function delete_directory($directory): bool
    {
        try {
            $this->driver->delete_directory($directory);
        } catch (Unable_To_Delete_Directory $e) {
            throw_if($this->throws_exceptions(), $e);
            $this->report($e);
            return false;
        }
        return true;
    }
    /**
     * Get the Flysystem driver.
     *
     * @return \League\Flysystem\FilesystemOperator
     */
    public function get_driver()
    {
        return $this->driver;
    }
    /**
     * Get the Flysystem adapter.
     */
    public function get_adapter(): \League\Flysystem\Filesystem_Adapter
    {
        return $this->adapter;
    }
    /**
     * Get the configuration values.
     */
    public function get_config(): array
    {
        return $this->config;
    }
    /**
     * Parse the given visibility value.
     *
     * @param  string|null  $visibility
     * @return string|null
     *
     * @throws \InvalidArgumentException
     */
    protected function parse_visibility($visibility)
    {
        if (is_null($visibility)) {
            return;
        }
        return match ($visibility) {
            Filesystem_Contract::VISIBILITY_PUBLIC => Visibility::PUBLIC,
            Filesystem_Contract::VISIBILITY_PRIVATE => Visibility::PRIVATE,
            default => throw new InvalidArgumentException("Unknown visibility: {$visibility}."),
        };
    }
    /**
     * Define a custom callback that generates file download responses.
     */
    public function serve_using(Closure $callback): void
    {
        $this->serve_callback = $callback;
    }
    /**
     * Define a custom temporary URL builder callback.
     */
    public function build_temporary_urls_using(Closure $callback): void
    {
        $this->temporary_url_callback = $callback;
    }
    /**
     * Define a custom temporary upload URL builder callback.
     */
    public function build_temporary_upload_urls_using(Closure $callback): void
    {
        $this->temporary_upload_url_callback = $callback;
    }
    /**
     * Determine if Flysystem exceptions should be thrown.
     */
    protected function throws_exceptions(): bool
    {
        return (bool) ($this->config['throw'] ?? false);
    }
    /**
     * Report the exception.
     *
     * @return void
     * @throws \Throwable
     */
    protected function report(\Throwable $exception)
    {
        if ($this->should_report() && Container::get_instance()->bound(Exception_Handler::class)) {
            Container::get_instance()->make(Exception_Handler::class)->report($exception);
        }
    }
    /**
     * Determine if Flysystem exceptions should be reported.
     */
    protected function should_report(): bool
    {
        return (bool) ($this->config['report'] ?? false);
    }
    /**
     * Pass dynamic methods call onto Flysystem.
     *
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        if (static::has_macro($method)) {
            return $this->macro_call($method, $parameters);
        }
        return $this->driver->{$method}(...$parameters);
    }
}