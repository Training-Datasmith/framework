<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Exceptions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
class Register_Error_View_Paths
{
    /**
     * Register the error view paths.
     */
    public function __invoke(): void
    {
        View::replace_namespace('errors', (new Collection(config('view.paths')))->map(fn($path): string => "{$path}/errors")->push(__DIR__ . '/views')->all());
    }
}