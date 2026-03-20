<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Contracts\Queue\Should_Queue;
use Illuminate\Foundation\Bus\Dispatchable;
class Queued_Command implements Should_Queue
{
    use Dispatchable;
    use Queueable;
    /**
     * Create a new job instance.
     *
     * @param  array  $data
     */
    public function __construct(
        /**
         * The data to pass to the Artisan command.
         */
        protected $data
    )
    {
    }
    /**
     * Handle the job.
     */
    public function handle(Kernel_Contract $kernel): void
    {
        $kernel->call(...array_values($this->data));
    }
    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function display_name()
    {
        return array_values($this->data)[0];
    }
}