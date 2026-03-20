<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Date;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'schedule:interrupt')]
class Schedule_Interrupt_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'schedule:interrupt';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interrupt the current schedule run';
    /**
     * Create a new schedule interrupt command.
     */
    public function __construct(
        /**
         * The cache store implementation.
         */
        protected \Illuminate\Contracts\Cache\Repository $cache
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->cache->put('illuminate:schedule:interrupt', true, Date::now()->end_of_minute());
        $this->components->info('Broadcasting schedule interrupt signal.');
    }
}