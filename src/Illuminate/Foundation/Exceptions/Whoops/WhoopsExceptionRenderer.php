<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Exceptions\Whoops;

use Illuminate\Contracts\Foundation\ExceptionRenderer;

use function tap;

use Whoops\Run as Whoops;

class WhoopsExceptionRenderer implements ExceptionRenderer
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
            $whoops->appendHandler($this->whoopsHandler());

            $whoops->writeToOutput(false);

            $whoops->allowQuit(false);
        })->handleException($throwable);
    }

    /**
     * Get the Whoops handler for the application.
     *
     * @return \Whoops\Handler\Handler
     */
    protected function whoopsHandler()
    {
        return (new WhoopsHandler())->forDebug();
    }
}
