<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Exceptions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

class RegisterErrorViewPaths
{
    /**
     * Register the error view paths.
     */
    public function __invoke(): void
    {
        View::replaceNamespace(
            'errors',
            (new Collection(config('view.paths')))
            ->map(fn ($path): string => "{$path}/errors")
            ->push(__DIR__.'/views')
            ->all()
        );
    }
}
