<?php

declare (strict_types=1);
namespace Illuminate\Filesystem;

use Illuminate\Contracts\Filesystem\Lock_Timeout_Exception;
class Lockable_File
{
    /**
     * The file resource.
     *
     * @var resource
     */
    protected $handle;
    /**
     * Indicates if the file is locked.
     *
     * @var bool
     */
    protected $is_locked = false;
    /**
     * Create a new File instance.
     *
     * @param  string  $path
     * @param  string  $mode
     */
    public function __construct(
        /**
         * The file path.
         */
        protected $path,
        $mode
    )
    {
        $this->ensure_directory_exists($this->path);
        $this->create_resource($this->path, $mode);
    }
    /**
     * Create the file's directory if necessary.
     *
     * @param  string  $path
     * @return void
     */
    protected function ensure_directory_exists($path)
    {
        if (!file_exists(dirname($path))) {
            @mkdir(dirname($path), 0777, true);
        }
    }
    /**
     * Create the file resource.
     *
     * @param  string  $path
     * @param  string  $mode
     * @return void
     *
     * @throws \Exception
     */
    protected function create_resource($path, $mode)
    {
        $this->handle = fopen($path, $mode);
    }
    /**
     * Read the file contents.
     *
     * @param  int|null  $length
     * @return string
     */
    public function read($length = null): string|false
    {
        clearstatcache(true, $this->path);
        return fread($this->handle, $length ?? ($this->size() ?: 1));
    }
    /**
     * Get the file size.
     *
     * @return int
     */
    public function size(): int|false
    {
        return filesize($this->path);
    }
    /**
     * Write to the file.
     *
     * @param  string  $contents
     * @return $this
     */
    public function write($contents): static
    {
        fwrite($this->handle, $contents);
        fflush($this->handle);
        return $this;
    }
    /**
     * Truncate the file.
     *
     * @return $this
     */
    public function truncate(): static
    {
        rewind($this->handle);
        ftruncate($this->handle, 0);
        return $this;
    }
    /**
     * Get a shared lock on the file.
     *
     * @param  bool  $block
     * @return $this
     *
     * @throws \Illuminate\Contracts\Filesystem\LockTimeoutException
     */
    public function get_shared_lock($block = false): static
    {
        if (!flock($this->handle, LOCK_SH | ($block ? 0 : LOCK_NB))) {
            throw new Lock_Timeout_Exception("Unable to acquire file lock at path [{$this->path}].");
        }
        $this->is_locked = true;
        return $this;
    }
    /**
     * Get an exclusive lock on the file.
     *
     * @param  bool  $block
     * @return $this
     *
     * @throws \Illuminate\Contracts\Filesystem\LockTimeoutException
     */
    public function get_exclusive_lock($block = false): static
    {
        if (!flock($this->handle, LOCK_EX | ($block ? 0 : LOCK_NB))) {
            throw new Lock_Timeout_Exception("Unable to acquire file lock at path [{$this->path}].");
        }
        $this->is_locked = true;
        return $this;
    }
    /**
     * Release the lock on the file.
     *
     * @return $this
     */
    public function release_lock(): static
    {
        flock($this->handle, LOCK_UN);
        $this->is_locked = false;
        return $this;
    }
    /**
     * Close the file.
     */
    public function close(): bool
    {
        if ($this->is_locked) {
            $this->release_lock();
        }
        return fclose($this->handle);
    }
}