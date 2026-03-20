<?php

declare (strict_types=1);
namespace Illuminate\Filesystem;

use Aws\S3\S3Client;
use Illuminate\Support\Traits\Conditionable;
use League\Flysystem\Filesystem_Adapter as FlysystemAdapter;
use League\Flysystem\Filesystem_Operator;
class Aws_S3v3adapter extends Filesystem_Adapter
{
    use Conditionable;
    /**
     * The AWS S3 client.
     *
     * @var \Aws\S3\S3Client
     */
    protected $client;
    /**
     * Create a new AwsS3V3FilesystemAdapter instance.
     */
    public function __construct(Filesystem_Operator $driver, Flysystem_Adapter $adapter, array $config, S3Client $client)
    {
        $config['directory_separator'] = '/';
        parent::__construct($driver, $adapter, $config);
        $this->client = $client;
    }
    /**
     * Get the URL for the file at the given path.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \RuntimeException
     */
    public function url($path)
    {
        // If an explicit base URL has been set on the disk configuration then we will use
        // it as the base URL instead of the default path. This allows the developer to
        // have full control over the base path for this filesystem's generated URLs.
        if (isset($this->config['url'])) {
            return $this->concat_path_to_url($this->config['url'], $this->prefixer->prefix_path($path));
        }
        return $this->client->get_object_url($this->config['bucket'], $this->prefixer->prefix_path($path));
    }
    /**
     * Determine if temporary URLs can be generated.
     */
    public function provides_temporary_urls(): bool
    {
        return true;
    }
    /**
     * Get a temporary URL for the file at the given path.
     *
     * @param  string  $path
     * @param  \DateTimeInterface  $expiration
     */
    public function temporary_url($path, $expiration, array $options = []): string
    {
        $command = $this->client->get_command('GetObject', array_merge(['Bucket' => $this->config['bucket'], 'Key' => $this->prefixer->prefix_path($path)], $options));
        $uri = $this->client->create_presigned_request($command, $expiration, $options)->get_uri();
        // If an explicit base URL has been set on the disk configuration then we will use
        // it as the base URL instead of the default path. This allows the developer to
        // have full control over the base path for this filesystem's generated URLs.
        if (isset($this->config['temporary_url'])) {
            $uri = $this->replace_base_url($uri, $this->config['temporary_url']);
        }
        return (string) $uri;
    }
    /**
     * Get a temporary upload URL for the file at the given path.
     *
     * @param  string  $path
     * @param  \DateTimeInterface  $expiration
     */
    public function temporary_upload_url($path, $expiration, array $options = []): array
    {
        $command = $this->client->get_command('PutObject', array_merge(['Bucket' => $this->config['bucket'], 'Key' => $this->prefixer->prefix_path($path)], $options));
        $signed_request = $this->client->create_presigned_request($command, $expiration, $options);
        $uri = $signed_request->get_uri();
        // If an explicit base URL has been set on the disk configuration then we will use
        // it as the base URL instead of the default path. This allows the developer to
        // have full control over the base path for this filesystem's generated URLs.
        if (isset($this->config['temporary_url'])) {
            $uri = $this->replace_base_url($uri, $this->config['temporary_url']);
        }
        return ['url' => (string) $uri, 'headers' => $signed_request->get_headers()];
    }
    /**
     * Get the underlying S3 client.
     *
     * @return \Aws\S3\S3Client
     */
    public function get_client()
    {
        return $this->client;
    }
}