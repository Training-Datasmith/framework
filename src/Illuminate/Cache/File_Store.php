<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Exception;
use Illuminate\Contracts\Cache\Lock_Provider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Filesystem\Lock_Timeout_Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\Lockable_File;
use Illuminate\Support\Interacts_With_Time;
class File_Store implements Store, Lock_Provider
{
    use Interacts_With_Time;
    use Retrieves_Multiple_Keys;
    /**
     * The file cache lock directory.
     *
     * @var string|null
     */
    protected $lock_directory;
    /**
     * Create a new file cache store instance.
     *
     * @param  string  $directory
     * @param  int|null  $filePermission
     * @param  array|bool|null  $serializableClasses
     */
    public function __construct(
        /**
         * The Illuminate Filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files,
        /**
         * The file cache directory.
         */
        protected $directory,
        /**
         * Octal representation of the cache file permissions.
         */
        protected $file_permission = null,
        /**
         * The classes that should be allowed during unserialization.
         */
        protected $serializable_classes = null
    )
    {
    }
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->get_payload($key)['data'] ?? null;
    }
    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     */
    public function put($key, $value, $seconds): bool
    {
        $this->ensure_cache_directory_exists($path = $this->path($key));
        $result = $this->files->put($path, $this->expiration($seconds) . serialize($value), true);
        if ($result !== false && $result > 0) {
            $this->ensure_permissions_are_correct($path);
            return true;
        }
        return false;
    }
    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     */
    public function add($key, $value, $seconds): bool
    {
        $this->ensure_cache_directory_exists($path = $this->path($key));
        $file = new Lockable_File($path, 'c+');
        try {
            $file->get_exclusive_lock();
        } catch (Lock_Timeout_Exception) {
            $file->close();
            return false;
        }
        $expire = $file->read(10);
        if (empty($expire) || $this->current_time() >= $expire) {
            $file->truncate()->write($this->expiration($seconds) . serialize($value))->close();
            $this->ensure_permissions_are_correct($path);
            return true;
        }
        $file->close();
        return false;
    }
    /**
     * Create the file cache directory if necessary.
     *
     * @param  string  $path
     * @return void
     */
    protected function ensure_cache_directory_exists($path)
    {
        $directory = dirname($path);
        if (!$this->files->exists($directory)) {
            $this->files->make_directory($directory, 0777, true, true);
            // We're creating two levels of directories (e.g. 7e/24), so we check them both...
            $this->ensure_permissions_are_correct($directory);
            $this->ensure_permissions_are_correct(dirname($directory));
        }
    }
    /**
     * Ensure the created node has the correct permissions.
     *
     * @param  string  $path
     * @return void
     */
    protected function ensure_permissions_are_correct($path)
    {
        if (is_null($this->file_permission) || intval($this->files->chmod($path), 8) == $this->file_permission) {
            return;
        }
        $this->files->chmod($path, $this->file_permission);
    }
    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int
     */
    public function increment($key, $value = 1)
    {
        $raw = $this->get_payload($key);
        return tap((int) $raw['data'] + $value, function ($new_value) use ($key, $raw): void {
            $this->put($key, $new_value, $raw['time'] ?? 0);
        });
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int
     */
    public function decrement($key, $value = 1)
    {
        return $this->increment($key, $value * -1);
    }
    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function forever($key, $value): bool
    {
        return $this->put($key, $value, 0);
    }
    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null): \Illuminate\Cache\File_Lock
    {
        $this->ensure_cache_directory_exists($this->lock_directory ?? $this->directory);
        return new File_Lock(new static($this->files, $this->lock_directory ?? $this->directory, $this->file_permission, $this->serializable_classes), "file-store-lock:{$name}", $seconds, $owner);
    }
    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restore_lock($name, $owner): \Illuminate\Cache\File_Lock
    {
        return $this->lock($name, 0, $owner);
    }
    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        if ($this->files->exists($file = $this->path($key))) {
            return tap($this->files->delete($file), function ($forgotten) use ($key): void {
                if ($forgotten && $this->files->exists($file = $this->path("illuminate:cache:flexible:created:{$key}"))) {
                    $this->files->delete($file);
                }
            });
        }
        return false;
    }
    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        if (!$this->files->is_directory($this->directory)) {
            return false;
        }
        foreach ($this->files->directories($this->directory) as $directory) {
            $deleted = $this->files->delete_directory($directory);
            if (!$deleted || $this->files->exists($directory)) {
                return false;
            }
        }
        return true;
    }
    /**
     * Retrieve an item and expiry time from the cache by key.
     *
     * @param  string  $key
     */
    protected function get_payload($key): array
    {
        $path = $this->path($key);
        // If the file doesn't exist, we obviously cannot return the cache so we will
        // just return null. Otherwise, we'll get the contents of the file and get
        // the expiration UNIX timestamps from the start of the file's contents.
        try {
            if (is_null($contents = $this->files->get($path, true))) {
                return $this->empty_payload();
            }
            $expire = substr($contents, 0, 10);
        } catch (Exception) {
            return $this->empty_payload();
        }
        // If the current time is greater than expiration timestamps we will delete
        // the file and return null. This helps clean up the old files and keeps
        // this directory much cleaner for us as old files aren't hanging out.
        if ($this->current_time() >= $expire) {
            $this->forget($key);
            return $this->empty_payload();
        }
        try {
            $data = $this->unserialize(substr($contents, 10));
        } catch (Exception) {
            $this->forget($key);
            return $this->empty_payload();
        }
        // Next, we'll extract the number of seconds that are remaining for a cache
        // so that we can properly retain the time for things like the increment
        // operation that may be performed on this cache on a later operation.
        $time = $expire - $this->current_time();
        return compact('data', 'time');
    }
    /**
     * Unserialize the given value.
     *
     * @param  string  $value
     */
    protected function unserialize($value): mixed
    {
        if ($this->serializable_classes !== null) {
            return unserialize($value, ['allowed_classes' => $this->serializable_classes]);
        }
        return unserialize($value);
    }
    /**
     * Get a default empty payload for the cache.
     */
    protected function empty_payload(): array
    {
        return ['data' => null, 'time' => null];
    }
    /**
     * Get the full path for the given cache key.
     *
     * @param  string  $key
     */
    public function path($key): string
    {
        $parts = array_slice(str_split($hash = sha1($key), 2), 0, 2);
        return $this->directory . '/' . implode('/', $parts) . '/' . $hash;
    }
    /**
     * Get the expiration time based on the given seconds.
     *
     * @param  int  $seconds
     * @return int
     */
    protected function expiration($seconds)
    {
        $time = $this->available_at($seconds);
        return $seconds === 0 || $time > 9999999999 ? 9999999999 : $time;
    }
    /**
     * Get the Filesystem instance.
     */
    public function get_filesystem(): \Illuminate\Filesystem\Filesystem
    {
        return $this->files;
    }
    /**
     * Get the working directory of the cache.
     *
     * @return string
     */
    public function get_directory()
    {
        return $this->directory;
    }
    /**
     * Set the working directory of the cache.
     *
     * @param  string  $directory
     * @return $this
     */
    public function set_directory($directory): static
    {
        $this->directory = $directory;
        return $this;
    }
    /**
     * Set the cache directory where locks should be stored.
     *
     * @param  string|null  $lockDirectory
     * @return $this
     */
    public function set_lock_directory($lock_directory): static
    {
        $this->lock_directory = $lock_directory;
        return $this;
    }
    /**
     * Get the cache key prefix.
     */
    public function get_prefix(): string
    {
        return '';
    }
}