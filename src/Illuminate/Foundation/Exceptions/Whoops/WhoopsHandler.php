<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Exceptions\Whoops;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Whoops\Handler\PrettyPageHandler;

class WhoopsHandler
{
    /**
     * Create a new Whoops handler for debug mode.
     *
     * @return \Whoops\Handler\PrettyPageHandler
     */
    public function forDebug()
    {
        return tap(new PrettyPageHandler(), function ($handler): void {
            $handler->handleUnconditionally(true);

            $this->registerApplicationPaths($handler)
                ->registerBlacklist($handler)
                ->registerEditor($handler);
        });
    }

    /**
     * Register the application paths with the handler.
     *
     * @param  \Whoops\Handler\PrettyPageHandler  $handler
     * @return $this
     */
    protected function registerApplicationPaths($handler): static
    {
        $handler->setApplicationPaths(
            array_flip($this->directoriesExceptVendor())
        );

        return $this;
    }

    /**
     * Get the application paths except for the "vendor" directory.
     */
    protected function directoriesExceptVendor(): array
    {
        return Arr::except(
            array_flip((new Filesystem())->directories(base_path())),
            [base_path('vendor')]
        );
    }

    /**
     * Register the blacklist with the handler.
     *
     * @param  \Whoops\Handler\PrettyPageHandler  $handler
     * @return $this
     */
    protected function registerBlacklist($handler): static
    {
        foreach (config('app.debug_blacklist', config('app.debug_hide', [])) as $key => $secrets) {
            foreach ($secrets as $secret) {
                $handler->blacklist($key, $secret);
            }
        }

        return $this;
    }

    /**
     * Register the editor with the handler.
     *
     * @param  \Whoops\Handler\PrettyPageHandler  $handler
     * @return $this
     */
    protected function registerEditor($handler): static
    {
        if (config('app.editor', false)) {
            $handler->setEditor(config('app.editor'));
        }

        return $this;
    }
}
