<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Foundation;

interface Exception_Renderer
{
    /**
     * Renders the given exception as HTML.
     *
     * @param  \Throwable  $throwable
     * @return string
     */
    public function render($throwable);
}