<?php

declare (strict_types=1);
namespace Illuminate\Filesystem;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Path_Traversal_Detected;
class Serve_File
{
    /**
     * Create a new invokable controller to serve files.
     */
    public function __construct(protected string $disk, protected array $config, protected bool $is_production)
    {
    }
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $path)
    {
        abort_unless($this->has_valid_signature($request), $this->is_production ? 404 : 403);
        try {
            abort_unless(Storage::disk($this->disk)->exists($path), 404);
            $headers = ['Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox"];
            return tap(Storage::disk($this->disk)->serve($request, $path, headers: $headers), function ($response) use ($headers): void {
                if (!$response->headers->has('Content-Security-Policy')) {
                    $response->headers->replace($headers);
                }
            });
        } catch (Path_Traversal_Detected) {
            abort(404);
        }
    }
    /**
     * Determine if the request has a valid signature if applicable.
     */
    protected function has_valid_signature(Request $request): bool
    {
        return !$request->boolean('upload') && (($this->config['visibility'] ?? 'private') === 'public' || $request->has_valid_relative_signature());
    }
}