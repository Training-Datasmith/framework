<?php

namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'event:clear')]
class EventClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'event:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all cached events and listeners';

    /**
     * Create a new config clear command instance.
     */
    public function __construct(/**
     * The filesystem instance.
     */
    protected \Illuminate\Filesystem\Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     *
     * @throws \RuntimeException
     */
    public function handle(): void
    {
        $this->files->delete($this->laravel->getCachedEventsPath());

        $this->components->info('Cached events cleared successfully.');
    }
}
