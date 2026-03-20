<?php

declare (strict_types=1);
namespace Illuminate\Filesystem;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Path_Traversal_Detected;
class Receive_File
{
    /**
     * Create a new invokable controller to receive files.
     */
    public function __construct(protected string $disk, protected array $config, protected bool $is_production)
    {
    }
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $path): Response
    {
        abort_unless($this->has_valid_signature($request), $this->is_production ? 404 : 403);
        try {
            Storage::disk($this->disk)->put($path, $request->get_content());
            return response()->no_content();
        } catch (Path_Traversal_Detected) {
            abort(404);
        }
    }
    /**
     * Determine if the request has a valid signature if applicable.
     */
    protected function has_valid_signature(Request $request): bool
    {
        return $request->boolean('upload') && $request->has_valid_relative_signature();
    }
}