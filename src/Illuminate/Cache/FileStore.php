<?php

declare(strict_types=1);

namespace Illuminate\Cache;

use Exception;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Filesystem\LockTimeoutException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\LockableFile;
use Illuminate\Support\InteractsWithTime;

class FileStore implements Store, LockProvider
{
    use InteractsWithTime;
    use RetrievesMultipleKeys;

    /**
     * The file cache lock directory.
     *
     * @var string|null
     */
    protected $lockDirectory;

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
        protected $filePermission = null,
        /**
         * The classes that should be allowed during unserialization.
         */
        protected $serializableClasses = null
    ) {
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->getPayload($key)['data'] ?? null;
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
        $this->ensureCacheDirectoryExists($path = $this->path($key));

        $result = $this->files->put(
            $path,
            $this->expiration($seconds).serialize($value),
            true
        );

        if ($result !== false && $result > 0) {
            $this->ensurePermissionsAreCorrect($path);

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
        $this->ensureCacheDirectoryExists($path = $this->path($key));

        $file = new LockableFile($path, 'c+');

        try {
            $file->getExclusiveLock();
        } catch (LockTimeoutException) {
            $file->close();

            return false;
        }

        $expire = $file->read(10);

        if (empty($expire) || $this->currentTime() >= $expire) {
            $file->truncate()
                ->write($this->expiration($seconds).serialize($value))
                ->close();

            $this->ensurePermissionsAreCorrect($path);

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
    protected function ensureCacheDirectoryExists($path)
    {
        $directory = dirname($path);

        if (! $this->files->exists($directory)) {
            $this->files->makeDirectory($directory, 0777, true, true);

            // We're creating two levels of directories (e.g. 7e/24), so we check them both...
            $this->ensurePermissionsAreCorrect($directory);
            $this->ensurePermissionsAreCorrect(dirname($directory));
        }
    }

    /**
     * Ensure the created node has the correct permissions.
     *
     * @param  string  $path
     * @return void
     */
    protected function ensurePermissionsAreCorrect($path)
    {
        if (is_null($this->filePermission) ||
            intval($this->files->chmod($path), 8) == $this->filePermission) {
            return;
        }

        $this->files->chmod($path, $this->filePermission);
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
        $raw = $this->getPayload($key);

        return tap(((int) $raw['data']) + $value, function ($newValue) use ($key, $raw): void {
            $this->put($key, $newValue, $raw['time'] ?? 0);
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
    public function lock($name, $seconds = 0, $owner = null): \Illuminate\Cache\FileLock
    {
        $this->ensureCacheDirectoryExists($this->lockDirectory ?? $this->directory);

        return new FileLock(
            new static($this->files, $this->lockDirectory ?? $this->directory, $this->filePermission, $this->serializableClasses),
            "file-store-lock:{$name}",
            $seconds,
            $owner
        );
    }

    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restoreLock($name, $owner): \Illuminate\Cache\FileLock
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
        if (! $this->files->isDirectory($this->directory)) {
            return false;
        }

        foreach ($this->files->directories($this->directory) as $directory) {
            $deleted = $this->files->deleteDirectory($directory);

            if (! $deleted || $this->files->exists($directory)) {
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
    protected function getPayload($key): array
    {
        $path = $this->path($key);

        // If the file doesn't exist, we obviously cannot return the cache so we will
        // just return null. Otherwise, we'll get the contents of the file and get
        // the expiration UNIX timestamps from the start of the file's contents.
        try {
            if (is_null($contents = $this->files->get($path, true))) {
                return $this->emptyPayload();
            }

            $expire = substr($contents, 0, 10);
        } catch (Exception) {
            return $this->emptyPayload();
        }

        // If the current time is greater than expiration timestamps we will delete
        // the file and return null. This helps clean up the old files and keeps
        // this directory much cleaner for us as old files aren't hanging out.
        if ($this->currentTime() >= $expire) {
            $this->forget($key);

            return $this->emptyPayload();
        }

        try {
            $data = $this->unserialize(substr($contents, 10));
        } catch (Exception) {
            $this->forget($key);

            return $this->emptyPayload();
        }

        // Next, we'll extract the number of seconds that are remaining for a cache
        // so that we can properly retain the time for things like the increment
        // operation that may be performed on this cache on a later operation.
        $time = $expire - $this->currentTime();

        return compact('data', 'time');
    }

    /**
     * Unserialize the given value.
     *
     * @param  string  $value
     */
    protected function unserialize($value): mixed
    {
        if ($this->serializableClasses !== null) {
            return unserialize($value, ['allowed_classes' => $this->serializableClasses]);
        }

        return unserialize($value);
    }

    /**
     * Get a default empty payload for the cache.
     */
    protected function emptyPayload(): array
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

        return $this->directory.'/'.implode('/', $parts).'/'.$hash;
    }

    /**
     * Get the expiration time based on the given seconds.
     *
     * @param  int  $seconds
     * @return int
     */
    protected function expiration($seconds)
    {
        $time = $this->availableAt($seconds);

        return $seconds === 0 || $time > 9999999999 ? 9999999999 : $time;
    }

    /**
     * Get the Filesystem instance.
     */
    public function getFilesystem(): \Illuminate\Filesystem\Filesystem
    {
        return $this->files;
    }

    /**
     * Get the working directory of the cache.
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Set the working directory of the cache.
     *
     * @param  string  $directory
     * @return $this
     */
    public function setDirectory($directory): static
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
    public function setLockDirectory($lockDirectory): static
    {
        $this->lockDirectory = $lockDirectory;

        return $this;
    }

    /**
     * Get the cache key prefix.
     */
    public function getPrefix(): string
    {
        return '';
    }
}
