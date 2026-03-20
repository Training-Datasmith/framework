<?php

declare (strict_types=1);
namespace Illuminate\Foundation;

use Illuminate\Support\Html_String;
use Illuminate\Support\Str;
class Mix
{
    /**
     * Get the path to a versioned Mix file.
     *
     * @param  string  $path
     * @param  string  $manifestDirectory
     *
     * @throws \Illuminate\Foundation\MixManifestNotFoundException|\Illuminate\Foundation\MixFileNotFoundException
     */
    public function __invoke($path, $manifest_directory = ''): \Illuminate\Support\Html_String|string
    {
        static $manifests = [];
        if (!str_starts_with($path, '/')) {
            $path = "/{$path}";
        }
        if ($manifest_directory && !str_starts_with($manifest_directory, '/')) {
            $manifest_directory = "/{$manifest_directory}";
        }
        if (is_file(public_path($manifest_directory . '/hot'))) {
            $url = rtrim(file_get_contents(public_path($manifest_directory . '/hot')));
            $custom_url = app('config')->get('app.mix_hot_proxy_url');
            if (!empty($custom_url)) {
                return new Html_String("{$custom_url}{$path}");
            }
            if (Str::starts_with($url, ['http://', 'https://'])) {
                return new Html_String(Str::after($url, ':') . $path);
            }
            return new Html_String("//localhost:8080{$path}");
        }
        $manifest_path = public_path($manifest_directory . '/mix-manifest.json');
        if (!isset($manifests[$manifest_path])) {
            if (!is_file($manifest_path)) {
                throw new Mix_Manifest_Not_Found_Exception("Mix manifest not found at: {$manifest_path}");
            }
            $manifests[$manifest_path] = json_decode(file_get_contents($manifest_path), true);
        }
        $manifest = $manifests[$manifest_path];
        if (!isset($manifest[$path])) {
            $exception = new Mix_File_Not_Found_Exception("Unable to locate Mix file: {$path}.");
            if (!app('config')->get('app.debug')) {
                report($exception);
                return $path;
            }
            throw $exception;
        }
        return new Html_String(app('config')->get('app.mix_url') . $manifest_directory . $manifest[$path]);
    }
}