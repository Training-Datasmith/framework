<?php

declare (strict_types=1);
namespace Illuminate\Http\Testing;

use LogicException;
class File_Factory
{
    /**
     * Create a new fake file.
     *
     * @param  string  $name
     * @param  string|int  $kilobytes
     * @param  string|null  $mimeType
     * @return \Illuminate\Http\Testing\File
     */
    public function create($name, $kilobytes = 0, $mime_type = null)
    {
        if (is_string($kilobytes)) {
            return $this->create_with_content($name, $kilobytes);
        }
        return tap(new File($name, tmpfile()), function ($file) use ($kilobytes, $mime_type): void {
            $file->size_to_report = $kilobytes * 1024;
            $file->mime_type_to_report = $mime_type;
        });
    }
    /**
     * Create a new fake file with content.
     *
     * @param  string  $name
     * @param  string  $content
     * @return \Illuminate\Http\Testing\File
     */
    public function create_with_content($name, $content)
    {
        $tmpfile = tmpfile();
        fwrite($tmpfile, $content);
        return tap(new File($name, $tmpfile), function ($file) use ($tmpfile): void {
            $file->size_to_report = fstat($tmpfile)['size'];
        });
    }
    /**
     * Create a new fake image.
     *
     * @param  string  $name
     * @param  int  $width
     * @param  int  $height
     *
     * @throws \LogicException
     */
    public function image($name, $width = 10, $height = 10): \Illuminate\Http\Testing\File
    {
        return new File($name, $this->generate_image($width, $height, pathinfo($name, PATHINFO_EXTENSION)));
    }
    /**
     * Generate a dummy image of the given width and height.
     *
     * @param  int  $width
     * @param  int  $height
     * @param  string  $extension
     * @return resource
     *
     * @throws \LogicException
     */
    protected function generate_image($width, $height, $extension)
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new LogicException('GD extension is not installed.');
        }
        return tap(tmpfile(), function ($temp) use ($width, $height, $extension): void {
            ob_start();
            $extension = in_array($extension, ['jpeg', 'png', 'gif', 'webp', 'wbmp', 'bmp']) ? strtolower($extension) : 'jpeg';
            $image = imagecreatetruecolor($width, $height);
            if (!function_exists($function_name = "image{$extension}")) {
                ob_get_clean();
                throw new LogicException("{$function_name} function is not defined and image cannot be generated.");
            }
            call_user_func($function_name, $image);
            fwrite($temp, ob_get_clean());
        });
    }
}