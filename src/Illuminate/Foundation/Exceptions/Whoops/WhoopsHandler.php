<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Exceptions\Whoops;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Whoops\Handler\Pretty_Page_Handler;
class Whoops_Handler
{
    /**
     * Create a new Whoops handler for debug mode.
     *
     * @return \Whoops\Handler\PrettyPageHandler
     */
    public function for_debug()
    {
        return tap(new Pretty_Page_Handler(), function ($handler): void {
            $handler->handle_unconditionally(true);
            $this->register_application_paths($handler)->register_blacklist($handler)->register_editor($handler);
        });
    }
    /**
     * Register the application paths with the handler.
     *
     * @param  \Whoops\Handler\PrettyPageHandler  $handler
     * @return $this
     */
    protected function register_application_paths($handler): static
    {
        $handler->set_application_paths(array_flip($this->directories_except_vendor()));
        return $this;
    }
    /**
     * Get the application paths except for the "vendor" directory.
     */
    protected function directories_except_vendor(): array
    {
        return Arr::except(array_flip((new Filesystem())->directories(base_path())), [base_path('vendor')]);
    }
    /**
     * Register the blacklist with the handler.
     *
     * @param  \Whoops\Handler\PrettyPageHandler  $handler
     * @return $this
     */
    protected function register_blacklist($handler): static
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
    protected function register_editor($handler): static
    {
        if (config('app.editor', false)) {
            $handler->set_editor(config('app.editor'));
        }
        return $this;
    }
}