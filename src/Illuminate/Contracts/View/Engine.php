<?php

declare(strict_types=1);

namespace Illuminate\Contracts\View;

interface Engine
{
    /**
     * Get the evaluated contents of the view.
     *
     * @param  string  $path
     * @return string
     */
    public function get($path, array $data = []);
}
