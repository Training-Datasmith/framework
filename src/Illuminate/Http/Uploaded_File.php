<?php

declare (strict_types=1);
namespace Illuminate\Http;

use Illuminate\Container\Container;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\File_Not_Found_Exception;
use Illuminate\Http\Testing\File_Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Macroable;
use Symfony\Component\Http_Foundation\File\Uploaded_File as SymfonyUploadedFile;
class Uploaded_File extends Symfony_Uploaded_File
{
    use File_Helpers;
    use Macroable;
    /**
     * Begin creating a new file fake.
     *
     * @return \Illuminate\Http\Testing\FileFactory
     */
    public static function fake()
    {
        return new File_Factory();
    }
    /**
     * Store the uploaded file on a filesystem disk.
     *
     * @param  string  $path
     * @param  array|string  $options
     * @return string|false
     */
    public function store($path = '', $options = [])
    {
        return $this->store_as($path, $this->hash_name(), $this->parse_options($options));
    }
    /**
     * Store the uploaded file on a filesystem disk with public visibility.
     *
     * @param  string  $path
     * @param  array|string  $options
     * @return string|false
     */
    public function store_publicly($path = '', $options = [])
    {
        $options = $this->parse_options($options);
        $options['visibility'] = 'public';
        return $this->store_as($path, $this->hash_name(), $options);
    }
    /**
     * Store the uploaded file on a filesystem disk with public visibility.
     *
     * @param  string  $path
     * @param  array|string|null  $name
     * @param  array|string  $options
     * @return string|false
     */
    public function store_publicly_as($path, $name = null, $options = [])
    {
        if (is_null($name) || is_array($name)) {
            [$path, $name, $options] = ['', $path, $name ?? []];
        }
        $options = $this->parse_options($options);
        $options['visibility'] = 'public';
        return $this->store_as($path, $name, $options);
    }
    /**
     * Store the uploaded file on a filesystem disk.
     *
     * @param  string  $path
     * @param  array|string|null  $name
     * @param  array|string  $options
     * @return string|false
     */
    public function store_as($path, $name = null, $options = [])
    {
        if (is_null($name) || is_array($name)) {
            [$path, $name, $options] = ['', $path, $name ?? []];
        }
        $options = $this->parse_options($options);
        $disk = Arr::pull($options, 'disk');
        return Container::get_instance()->make(Filesystem_Factory::class)->disk($disk)->put_file_as($path, $this, $name, $options);
    }
    /**
     * Get the contents of the uploaded file.
     *
     * @return false|string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function get()
    {
        if (!$this->is_valid()) {
            throw new File_Not_Found_Exception("File does not exist at path {$this->get_pathname()}.");
        }
        return file_get_contents($this->get_pathname());
    }
    /**
     * Get the file's extension supplied by the client.
     *
     * @return string
     */
    public function client_extension()
    {
        return $this->guess_client_extension();
    }
    /**
     * Create a new file instance from a base instance.
     *
     * @param  bool  $test
     * @return static
     */
    public static function create_from_base(Symfony_Uploaded_File $file, $test = false)
    {
        return $file instanceof static ? $file : new static($file->get_pathname(), $file->get_client_original_path(), $file->get_client_mime_type(), $file->get_error(), $test);
    }
    /**
     * Parse and format the given options.
     *
     * @param  array|string  $options
     * @return array
     */
    protected function parse_options($options)
    {
        if (is_string($options)) {
            return ['disk' => $options];
        }
        return $options;
    }
}