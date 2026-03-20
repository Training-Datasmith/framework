<?php

declare (strict_types=1);
namespace Illuminate\Filesystem;

use ErrorException;
use Filesystem_Iterator;
use Illuminate\Contracts\Filesystem\File_Not_Found_Exception;
use Illuminate\Support\Lazy_Collection;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use RuntimeException;
use Spl_File_Object;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\Mime_Types;
class Filesystem
{
    use Conditionable;
    use Macroable;
    /**
     * Determine if a file or directory exists.
     *
     * @param  string  $path
     */
    public function exists($path): bool
    {
        return file_exists($path);
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
     * Get the contents of a file.
     *
     * @param  string  $path
     * @param  bool  $lock
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function get($path, $lock = false): string|false
    {
        if ($this->is_file($path)) {
            return $lock ? $this->shared_get($path) : file_get_contents($path);
        }
        throw new File_Not_Found_Exception("File does not exist at path {$path}.");
    }
    /**
     * Get the contents of a file as decoded JSON.
     *
     * @param  string  $path
     * @param  int  $flags
     * @param  bool  $lock
     * @return array
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function json($path, $flags = 0, $lock = false): mixed
    {
        return json_decode($this->get($path, $lock), true, 512, $flags);
    }
    /**
     * Get contents of a file with shared access.
     *
     * @param  string  $path
     * @return string
     */
    public function shared_get($path): string|false
    {
        $contents = '';
        $handle = fopen($path, 'rb');
        if ($handle) {
            try {
                if (flock($handle, LOCK_SH)) {
                    clearstatcache(true, $path);
                    $contents = stream_get_contents($handle);
                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }
        return $contents;
    }
    /**
     * Get the returned value of a file.
     *
     * @param  string  $path
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function get_require($path, array $data = [])
    {
        if ($this->is_file($path)) {
            $__path = $path;
            $__data = $data;
            return (static function () use ($__path, $__data) {
                extract($__data, EXTR_SKIP);
                return require $__path;
            })();
        }
        throw new File_Not_Found_Exception("File does not exist at path {$path}.");
    }
    /**
     * Require the given file once.
     *
     * @param  string  $path
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function require_once($path, array $data = [])
    {
        if ($this->is_file($path)) {
            $__path = $path;
            $__data = $data;
            return (static function () use ($__path, $__data) {
                extract($__data, EXTR_SKIP);
                return require_once $__path;
            })();
        }
        throw new File_Not_Found_Exception("File does not exist at path {$path}.");
    }
    /**
     * Get the contents of a file one line at a time.
     *
     * @param  string  $path
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function lines($path): \Illuminate\Support\Lazy_Collection
    {
        if (!$this->is_file($path)) {
            throw new File_Not_Found_Exception("File does not exist at path {$path}.");
        }
        return new Lazy_Collection(function () use ($path) {
            $file = new Spl_File_Object($path);
            $file->set_flags(Spl_File_Object::DROP_NEW_LINE);
            while (!$file->eof()) {
                yield $file->fgets();
            }
        });
    }
    /**
     * Get the hash of the file at the given path.
     *
     * @param  string  $path
     * @param  string  $algorithm
     * @return string|false
     */
    public function hash($path, $algorithm = 'md5')
    {
        return hash_file($algorithm, $path);
    }
    /**
     * Write the contents of a file.
     *
     * @param  string  $path
     * @param  string  $contents
     * @param  bool  $lock
     */
    public function put($path, $contents, $lock = false): int|false
    {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }
    /**
     * Write the contents of a file, replacing it atomically if it already exists.
     *
     * @param  string  $path
     * @param  string  $content
     * @param  int|null  $mode
     */
    public function replace($path, $content, $mode = null): void
    {
        // If the path already exists and is a symlink, get the real path...
        clearstatcache(true, $path);
        $path = realpath($path) ?: $path;
        $temp_path = tempnam(dirname($path), basename($path));
        // Fix permissions of tempPath because `tempnam()` creates it with permissions set to 0600...
        if (!is_null($mode)) {
            chmod($temp_path, $mode);
        } else {
            chmod($temp_path, 0777 - umask());
        }
        file_put_contents($temp_path, $content);
        rename($temp_path, $path);
    }
    /**
     * Replace a given string within a given file.
     *
     * @param  array|string  $search
     * @param  array|string  $replace
     * @param  string  $path
     */
    public function replace_in_file($search, $replace, $path): void
    {
        file_put_contents($path, str_replace($search, $replace, file_get_contents($path)));
    }
    /**
     * Prepend to a file.
     *
     * @param  string  $path
     * @return int
     */
    public function prepend($path, string $data): int|false
    {
        if ($this->exists($path)) {
            return $this->put($path, $data . $this->get($path));
        }
        return $this->put($path, $data);
    }
    /**
     * Append to a file.
     *
     * @param  string  $path
     * @param  string  $data
     * @param  bool  $lock
     * @return int
     */
    public function append($path, $data, $lock = false): int|false
    {
        return file_put_contents($path, $data, FILE_APPEND | ($lock ? LOCK_EX : 0));
    }
    /**
     * Get or set UNIX mode of a file or directory.
     *
     * @param  string  $path
     * @param  int|null  $mode
     */
    public function chmod($path, $mode = null): bool|string
    {
        if ($mode) {
            return chmod($path, $mode);
        }
        return substr(sprintf('%o', fileperms($path)), -4);
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
                if (@unlink($path)) {
                    clearstatcache(false, $path);
                } else {
                    $success = false;
                }
            } catch (ErrorException) {
                $success = false;
            }
        }
        return $success;
    }
    /**
     * Move a file to a new location.
     *
     * @param  string  $path
     * @param  string  $target
     */
    public function move($path, $target): bool
    {
        return rename($path, $target);
    }
    /**
     * Copy a file to a new location.
     *
     * @param  string  $path
     * @param  string  $target
     */
    public function copy($path, $target): bool
    {
        return copy($path, $target);
    }
    /**
     * Create a symlink to the target file or directory. On Windows, a hard link is created if the target is a file.
     *
     * @param  string  $target
     * @param  string  $link
     * @return bool|null
     */
    public function link($target, $link)
    {
        if (!windows_os()) {
            if (function_exists('symlink')) {
                return symlink($target, $link);
            }
            return exec('ln -s ' . escapeshellarg($target) . ' ' . escapeshellarg($link)) !== false;
        }
        $mode = $this->is_directory($target) ? 'J' : 'H';
        exec("mklink /{$mode} " . escapeshellarg($link) . ' ' . escapeshellarg($target));
    }
    /**
     * Create a relative symlink to the target file or directory.
     *
     * @param  string  $target
     * @param  string  $link
     *
     * @throws \RuntimeException
     */
    public function relative_link($target, $link): void
    {
        if (!class_exists(Symfony_Filesystem::class)) {
            throw new RuntimeException('To enable support for relative links, please install the symfony/filesystem package.');
        }
        $relative_target = (new Symfony_Filesystem())->make_path_relative($target, dirname($link));
        $this->link($this->is_file($target) ? rtrim($relative_target, '/') : $relative_target, $link);
    }
    /**
     * Extract the file name from a file path.
     *
     * @param  string  $path
     */
    public function name($path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }
    /**
     * Extract the trailing name component from a file path.
     *
     * @param  string  $path
     */
    public function basename($path): string
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }
    /**
     * Extract the parent directory from a file path.
     *
     * @param  string  $path
     */
    public function dirname($path): string
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }
    /**
     * Extract the file extension from a file path.
     *
     * @param  string  $path
     */
    public function extension($path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }
    /**
     * Guess the file extension from the MIME type of a given file.
     *
     * @param  string  $path
     * @return string|null
     *
     * @throws \RuntimeException
     */
    public function guess_extension($path)
    {
        if (!class_exists(Mime_Types::class)) {
            throw new RuntimeException('To enable support for guessing extensions, please install the symfony/mime package.');
        }
        return (new Mime_Types())->get_extensions($this->mime_type($path))[0] ?? null;
    }
    /**
     * Get the file type of a given file.
     *
     * @param  string  $path
     * @return string|false
     */
    public function type($path): string|false
    {
        return filetype($path);
    }
    /**
     * Get the MIME type of a given file.
     *
     * @param  string  $path
     * @return string|false
     */
    public function mime_type($path): string|false
    {
        return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
    }
    /**
     * Get the file size of a given file.
     *
     * @param  string  $path
     * @return int
     */
    public function size($path): int|false
    {
        return filesize($path);
    }
    /**
     * Get the file's last modification time.
     *
     * @param  string  $path
     * @return int
     */
    public function last_modified($path): int|false
    {
        return filemtime($path);
    }
    /**
     * Determine if the given path is a directory.
     *
     * @param  string  $directory
     */
    public function is_directory($directory): bool
    {
        return is_dir($directory);
    }
    /**
     * Determine if the given path is a directory that does not contain any other files or directories.
     *
     * @param  string  $directory
     */
    public function is_empty_directory(string|array $directory, bool $ignore_dot_files = false): bool
    {
        return !Finder::create()->ignore_dot_files($ignore_dot_files)->in($directory)->depth(0)->has_results();
    }
    /**
     * Determine if the given path is readable.
     *
     * @param  string  $path
     */
    public function is_readable($path): bool
    {
        return is_readable($path);
    }
    /**
     * Determine if the given path is writable.
     *
     * @param  string  $path
     */
    public function is_writable($path): bool
    {
        return is_writable($path);
    }
    /**
     * Determine if two files are the same by comparing their hashes.
     *
     * @param  string  $firstFile
     * @param  string  $secondFile
     */
    public function has_same_hash($first_file, $second_file): bool
    {
        $hash = @hash_file('xxh128', $first_file);
        return $hash && hash_equals($hash, (string) @hash_file('xxh128', $second_file));
    }
    /**
     * Determine if the given path is a file.
     *
     * @param  string  $file
     */
    public function is_file($file): bool
    {
        return is_file($file);
    }
    /**
     * Find path names matching a given pattern.
     *
     * @param  string  $pattern
     * @param  int  $flags
     * @return array
     */
    public function glob($pattern, $flags = 0)
    {
        return glob($pattern, $flags);
    }
    /**
     * Get an array of all files in a directory.
     *
     * @param  string  $directory
     * @param  bool  $hidden
     * @return \Symfony\Component\Finder\SplFileInfo[]
     */
    public function files(string|array $directory, $hidden = false, array|string|int $depth = 0): array
    {
        return iterator_to_array(Finder::create()->files()->ignore_dot_files(!$hidden)->in($directory)->depth($depth)->sort_by_name(), false);
    }
    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param  string  $directory
     * @param  bool  $hidden
     * @return \Symfony\Component\Finder\SplFileInfo[]
     */
    public function all_files(string|array $directory, $hidden = false): array
    {
        return $this->files($directory, $hidden, []);
    }
    /**
     * Get all of the directories within a given directory.
     *
     * @param  string  $directory
     */
    public function directories(string|array $directory, array|string|int $depth = 0): array
    {
        $directories = [];
        foreach (Finder::create()->in($directory)->directories()->depth($depth)->sort_by_name() as $dir) {
            $directories[] = $dir->get_pathname();
        }
        return $directories;
    }
    /**
     * Get all the directories within a given directory (recursive).
     */
    public function all_directories(string $directory): array
    {
        return $this->directories($directory, []);
    }
    /**
     * Ensure a directory exists.
     *
     * @param  string  $path
     * @param  int  $mode
     * @param  bool  $recursive
     */
    public function ensure_directory_exists($path, $mode = 0755, $recursive = true): void
    {
        if (!$this->is_directory($path)) {
            $this->make_directory($path, $mode, $recursive);
        }
    }
    /**
     * Create a directory.
     *
     * @param  string  $path
     * @param  int  $mode
     * @param  bool  $recursive
     * @param  bool  $force
     * @return bool
     */
    public function make_directory($path, $mode = 0755, $recursive = false, $force = false)
    {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }
        return mkdir($path, $mode, $recursive);
    }
    /**
     * Move a directory.
     *
     * @param  string  $from
     * @param  string  $to
     * @param  bool  $overwrite
     * @return bool
     */
    public function move_directory($from, $to, $overwrite = false)
    {
        if ($overwrite && $this->is_directory($to) && !$this->delete_directory($to)) {
            return false;
        }
        return @rename($from, $to) === true;
    }
    /**
     * Copy a directory from one location to another.
     *
     * @param  string  $directory
     * @param  int|null  $options
     */
    public function copy_directory($directory, string $destination, $options = null): bool
    {
        if (!$this->is_directory($directory)) {
            return false;
        }
        $options = $options ?: Filesystem_Iterator::SKIP_DOTS;
        // If the destination directory does not actually exist, we will go ahead and
        // create it recursively, which just gets the destination prepared to copy
        // the files over. Once we make the directory we'll proceed the copying.
        $this->ensure_directory_exists($destination, 0777);
        $items = new Filesystem_Iterator($directory, $options);
        foreach ($items as $item) {
            // As we spin through items, we will check to see if the current file is actually
            // a directory or a file. When it is actually a directory we will need to call
            // back into this function recursively to keep copying these nested folders.
            $target = $destination . '/' . $item->get_basename();
            if ($item->is_dir()) {
                $path = $item->get_pathname();
                if (!$this->copy_directory($path, $target, $options)) {
                    return false;
                }
            } elseif (!$this->copy($item->get_pathname(), $target)) {
                return false;
            }
        }
        return true;
    }
    /**
     * Recursively delete a directory.
     *
     * The directory itself may be optionally preserved.
     *
     * @param  string  $directory
     * @param  bool  $preserve
     */
    public function delete_directory($directory, $preserve = false): bool
    {
        if (!$this->is_directory($directory)) {
            return false;
        }
        $items = new Filesystem_Iterator($directory);
        foreach ($items as $item) {
            // If the item is a directory, we can just recurse into the function and
            // delete that sub-directory otherwise we'll just delete the file and
            // keep iterating through each file until the directory is cleaned.
            if ($item->is_dir() && !$item->is_link()) {
                $this->delete_directory($item->get_pathname());
            } else {
                $this->delete($item->get_pathname());
            }
        }
        unset($items);
        if (!$preserve) {
            @rmdir($directory);
        }
        return true;
    }
    /**
     * Remove all of the directories within a given directory.
     *
     * @param  string  $directory
     */
    public function delete_directories(string|array $directory): bool
    {
        $all_directories = $this->directories($directory);
        if (!empty($all_directories)) {
            foreach ($all_directories as $directory_name) {
                $this->delete_directory($directory_name);
            }
            return true;
        }
        return false;
    }
    /**
     * Empty the specified directory of all files and folders.
     *
     * @param  string  $directory
     */
    public function clean_directory($directory): bool
    {
        return $this->delete_directory($directory, true);
    }
}