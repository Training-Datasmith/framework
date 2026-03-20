<?php

declare (strict_types=1);
namespace Illuminate\Http;

use Illuminate\Support\Str;
trait File_Helpers
{
    /**
     * The cache copy of the file's hash name.
     *
     * @var string|null
     */
    protected $hash_name;
    /**
     * Get the fully-qualified path to the file.
     *
     * @return string
     */
    public function path()
    {
        return $this->get_real_path();
    }
    /**
     * Get the file's extension.
     *
     * @return string
     */
    public function extension()
    {
        return $this->guess_extension();
    }
    /**
     * Get a filename for the file.
     *
     * @param  string|null  $path
     * @return string
     */
    public function hash_name($path = null)
    {
        if ($path) {
            $path = rtrim($path, '/') . '/';
        }
        $hash = $this->hash_name ?: $this->hash_name = Str::random(40);
        if ($extension = $this->guess_extension()) {
            $extension = '.' . $extension;
        }
        return $path . $hash . $extension;
    }
    /**
     * Get the dimensions of the image (if applicable).
     *
     * @return array|null
     */
    public function dimensions(): array|false
    {
        return @getimagesize($this->get_real_path());
    }
}