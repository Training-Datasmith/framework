<?php

declare(strict_types=1);

namespace Illuminate\View\Engines;

use Illuminate\Contracts\View\Engine;
use Illuminate\Filesystem\Filesystem;

class FileEngine implements Engine
{
    /**
     * Create a new file engine instance.
     */
    public function __construct(
        /**
         * The filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files
    ) {
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @param  string  $path
     * @return string
     */
    public function get($path, array $data = []): string|false
    {
        return $this->files->get($path);
    }
}
