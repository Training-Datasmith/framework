<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
trait With_Console_Events
{
    /**
     * Register console events.
     *
     * @return void
     */
    protected function set_up_with_console_events()
    {
        $this->app[Console_Kernel::class]->reroute_symfony_command_events();
    }
}