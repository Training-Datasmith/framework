<?php

declare (strict_types=1);
namespace Illuminate\Filesystem;

use Closure;
use Illuminate\Support\Traits\Conditionable;
use RuntimeException;
class Local_Filesystem_Adapter extends Filesystem_Adapter
{
    use Conditionable;
    /**
     * The name of the filesystem disk.
     *
     * @var string
     */
    protected $disk;
    /**
     * Indicates if signed URLs should serve corresponding files.
     *
     * @var bool
     */
    protected $should_serve_signed_urls = false;
    /**
     * The Closure that should be used to resolve the URL generator.
     *
     * @var \Closure
     */
    protected $url_generator_resolver;
    /**
     * Determine if temporary URLs can be generated.
     */
    public function provides_temporary_urls(): bool
    {
        return $this->temporary_url_callback || $this->should_serve_signed_urls && $this->url_generator_resolver instanceof Closure;
    }
    /**
     * Determine if temporary upload URLs can be generated.
     */
    public function provides_temporary_upload_urls(): bool
    {
        return $this->temporary_upload_url_callback || $this->should_serve_signed_urls && $this->url_generator_resolver instanceof Closure;
    }
    /**
     * Get a temporary URL for the file at the given path.
     *
     * @param  string  $path
     * @param  \DateTimeInterface  $expiration
     * @return string
     */
    public function temporary_url($path, $expiration, array $options = [])
    {
        if ($this->temporary_url_callback) {
            return $this->temporary_url_callback->bind_to($this, static::class)($path, $expiration, $options);
        }
        if (!$this->provides_temporary_urls()) {
            throw new RuntimeException('This driver does not support creating temporary URLs.');
        }
        $url = call_user_func($this->url_generator_resolver);
        return $url->to($url->temporary_signed_route('storage.' . $this->disk, $expiration, ['path' => $path], absolute: false));
    }
    /**
     * Get a temporary upload URL for the file at the given path.
     *
     * @param  string  $path
     * @param  \DateTimeInterface  $expiration
     * @return array
     */
    public function temporary_upload_url($path, $expiration, array $options = [])
    {
        if ($this->temporary_upload_url_callback) {
            return $this->temporary_upload_url_callback->bind_to($this, static::class)($path, $expiration, $options);
        }
        if (!$this->provides_temporary_upload_urls()) {
            throw new RuntimeException('This driver does not support creating temporary upload URLs.');
        }
        $url = call_user_func($this->url_generator_resolver);
        return ['url' => $url->to($url->temporary_signed_route('storage.' . $this->disk . '.upload', $expiration, ['path' => $path, 'upload' => true], absolute: false)), 'headers' => []];
    }
    /**
     * Specify the name of the disk the adapter is managing.
     *
     * @return $this
     */
    public function disk_name(string $disk): static
    {
        $this->disk = $disk;
        return $this;
    }
    /**
     * Indicate that signed URLs should serve the corresponding files.
     *
     * @return $this
     */
    public function should_serve_signed_urls(bool $serve = true, ?Closure $url_generator_resolver = null): static
    {
        $this->should_serve_signed_urls = $serve;
        $this->url_generator_resolver = $url_generator_resolver;
        return $this;
    }
}