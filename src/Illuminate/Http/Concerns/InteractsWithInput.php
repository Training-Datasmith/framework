<?php

declare(strict_types=1);

namespace Illuminate\Http\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Support\Traits\Dumpable;
use Illuminate\Support\Traits\InteractsWithData;
use SplFileInfo;
use Symfony\Component\HttpFoundation\InputBag;

trait InteractsWithInput
{
    use Dumpable;
    use InteractsWithData;

    /**
     * Retrieve a server variable from the request.
     *
     * @param  string|null  $key
     * @param  string|array|null  $default
     * @return string|array|null
     */
    public function server($key = null, $default = null)
    {
        return $this->retrieveItem('server', $key, $default);
    }

    /**
     * Determine if a header is set on the request.
     *
     * @param  string  $key
     */
    public function hasHeader($key): bool
    {
        return ! is_null($this->header($key));
    }

    /**
     * Retrieve a header from the request.
     *
     * @param  string|null  $key
     * @param  string|array|null  $default
     * @return string|array|null
     */
    public function header($key = null, $default = null)
    {
        return $this->retrieveItem('headers', $key, $default);
    }

    /**
     * Get the bearer token from the request headers.
     *
     * @return string|null
     */
    public function bearerToken()
    {
        $header = $this->header('Authorization', '');

        $position = strripos((string) $header, 'Bearer ');

        if ($position !== false) {
            $header = substr((string) $header, $position + 7);

            return str_contains($header, ',') ? strstr($header, ',', true) : $header;
        }
    }

    /**
     * Get the keys for all of the input and files.
     */
    public function keys(): array
    {
        return array_merge(array_keys($this->input()), $this->files->keys());
    }

    /**
     * Get all of the input and files for the request.
     *
     * @param  mixed  $keys
     */
    public function all($keys = null): array
    {
        $input = array_replace_recursive($this->input(), $this->allFiles());

        if (! $keys) {
            return $input;
        }

        $results = [];

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            Arr::set($results, $key, Arr::get($input, $key));
        }

        return $results;
    }

    /**
     * Retrieve an input item from the request.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function input($key = null, $default = null)
    {
        return data_get(
            $this->getInputSource()->all() + $this->query->all(),
            $key,
            $default
        );
    }

    /**
     * Retrieve input from the request as a Fluent object instance.
     *
     * @param  array|string|null  $key
     */
    public function fluent($key = null, array $default = []): \Illuminate\Support\Fluent
    {
        $value = is_array($key) ? $this->only($key) : $this->input($key);

        return new Fluent($value ?? $default);
    }

    /**
     * Retrieve a query string item from the request.
     *
     * @param  string|null  $key
     * @param  string|array|null  $default
     * @return string|array|null
     */
    public function query($key = null, $default = null)
    {
        return $this->retrieveItem('query', $key, $default);
    }

    /**
     * Retrieve a request payload item from the request.
     *
     * @param  string|null  $key
     * @param  string|array|null  $default
     * @return string|array|null
     */
    public function post($key = null, $default = null)
    {
        return $this->retrieveItem('request', $key, $default);
    }

    /**
     * Determine if a cookie is set on the request.
     *
     * @param  string  $key
     */
    public function hasCookie($key): bool
    {
        return ! is_null($this->cookie($key));
    }

    /**
     * Retrieve a cookie from the request.
     *
     * @param  string|null  $key
     * @param  string|array|null  $default
     * @return string|array|null
     */
    public function cookie($key = null, $default = null)
    {
        return $this->retrieveItem('cookies', $key, $default);
    }

    /**
     * Get an array of all of the files on the request.
     *
     * @return array<string, \Illuminate\Http\UploadedFile|\Illuminate\Http\UploadedFile[]>
     */
    public function allFiles()
    {
        $files = $this->files->all();

        return $this->convertedFiles ??= $this->convertUploadedFiles($files);
    }

    /**
     * Convert the given array of Symfony UploadedFiles to custom Laravel UploadedFiles.
     *
     * @param  array<string, \Symfony\Component\HttpFoundation\File\UploadedFile|\Symfony\Component\HttpFoundation\File\UploadedFile[]>  $files
     * @return array<string, \Illuminate\Http\UploadedFile|\Illuminate\Http\UploadedFile[]>
     */
    protected function convertUploadedFiles(array $files): array
    {
        return array_map(function (array|\Symfony\Component\HttpFoundation\File\UploadedFile $file) {
            if (is_array($file) && empty(array_filter($file))) {
                return $file;
            }

            return is_array($file)
                ? $this->convertUploadedFiles($file)
                : UploadedFile::createFromBase($file);
        }, $files);
    }

    /**
     * Determine if the uploaded data contains a file.
     *
     * @param  string  $key
     */
    public function hasFile($key): bool
    {
        if (! is_array($files = $this->file($key))) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if ($this->isValidFile($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check that the given file is a valid file instance.
     *
     * @param  mixed  $file
     */
    protected function isValidFile($file): bool
    {
        return $file instanceof SplFileInfo && $file->getPath() !== '';
    }

    /**
     * Retrieve a file from the request.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return ($key is null ? array<string, \Illuminate\Http\UploadedFile|\Illuminate\Http\UploadedFile[]> : \Illuminate\Http\UploadedFile|\Illuminate\Http\UploadedFile[]|null)
     */
    public function file($key = null, $default = null)
    {
        return data_get($this->allFiles(), $key, $default);
    }

    /**
     * Retrieve data from the instance.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    protected function data($key = null, $default = null)
    {
        return $this->input($key, $default);
    }

    /**
     * Retrieve a parameter item from a given source.
     *
     * @param  string  $source
     * @param  string|null  $key
     * @param  string|array|null  $default
     * @return string|array|null
     */
    protected function retrieveItem($source, $key, $default)
    {
        if (is_null($key)) {
            return $this->$source->all();
        }

        if ($this->$source instanceof InputBag) {
            return $this->$source->all()[$key] ?? $default;
        }

        return $this->$source->get($key, $default);
    }

    /**
     * Dump the items.
     *
     * @param  mixed  $keys
     * @return $this
     */
    public function dump($keys = [])
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        dump(count($keys) > 0 ? $this->only($keys) : $this->all());

        return $this;
    }
}
