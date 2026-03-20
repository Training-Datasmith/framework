<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Exceptions\Whoops;

use Illuminate\Contracts\Foundation\Exception_Renderer;
use function tap;
use Whoops\Run as Whoops;
class Whoops_Exception_Renderer implements Exception_Renderer
{
    /**
     * Renders the given exception as HTML.
     *
     * @param  \Throwable  $throwable
     * @return string
     */
    public function render($throwable)
    {
        return tap(new Whoops(), function ($whoops): void {
            $whoops->append_handler($this->whoops_handler());
            $whoops->write_to_output(false);
            $whoops->allow_quit(false);
        })->handle_exception($throwable);
    }
    /**
     * Get the Whoops handler for the application.
     *
     * @return \Whoops\Handler\Handler
     */
    protected function whoops_handler()
    {
        return (new Whoops_Handler())->for_debug();
    }
}