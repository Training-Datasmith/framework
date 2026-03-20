<?php

declare (strict_types=1);
namespace Illuminate\Http\Testing;

use Illuminate\Http\Uploaded_File;
class File extends Uploaded_File
{
    /**
     * The "size" to report.
     *
     * @var int
     */
    public $size_to_report;
    /**
     * The MIME type to report.
     *
     * @var string|null
     */
    public $mime_type_to_report;
    /**
     * Create a new file instance.
     *
     * @param  string  $name
     * @param  resource  $tempFile
     */
    public function __construct(
        /**
         * The name of the file.
         */
        public $name,
        /**
         * The temporary file resource.
         */
        public $temp_file
    )
    {
        parent::__construct($this->temp_file_path(), $this->name, $this->get_mime_type(), null, true);
    }
    /**
     * Create a new fake file.
     *
     * @param  string  $name
     * @param  string|int  $kilobytes
     * @return \Illuminate\Http\Testing\File
     */
    public static function create($name, $kilobytes = 0)
    {
        return (new File_Factory())->create($name, $kilobytes);
    }
    /**
     * Create a new fake file with content.
     *
     * @param  string  $name
     * @param  string  $content
     * @return \Illuminate\Http\Testing\File
     */
    public static function create_with_content($name, $content)
    {
        return (new File_Factory())->create_with_content($name, $content);
    }
    /**
     * Create a new fake image.
     *
     * @param  string  $name
     * @param  int  $width
     * @param  int  $height
     * @return \Illuminate\Http\Testing\File
     */
    public static function image($name, $width = 10, $height = 10)
    {
        return (new File_Factory())->image($name, $width, $height);
    }
    /**
     * Set the "size" of the file in kilobytes.
     *
     * @param  int  $kilobytes
     * @return $this
     */
    public function size($kilobytes)
    {
        $this->size_to_report = $kilobytes * 1024;
        return $this;
    }
    /**
     * Get the size of the file.
     */
    public function get_size(): int
    {
        return $this->size_to_report ?: parent::get_size();
    }
    /**
     * Set the MIME type for the file.
     *
     * @param  string  $mimeType
     * @return $this
     */
    public function mime_type($mime_type)
    {
        $this->mime_type_to_report = $mime_type;
        return $this;
    }
    /**
     * Get the MIME type of the file.
     */
    public function get_mime_type(): string
    {
        return $this->mime_type_to_report ?: Mime_Type::from($this->name);
    }
    /**
     * Get the path to the temporary file.
     *
     * @return string
     */
    protected function temp_file_path()
    {
        return stream_get_meta_data($this->temp_file)['uri'];
    }
}