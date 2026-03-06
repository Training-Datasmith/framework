<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'view:clear')]
class ViewClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'view:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all compiled view files';

    /**
     * Create a new config clear command instance.
     */
    public function __construct(/**
     * The filesystem instance.
     */
        protected \Illuminate\Filesystem\Filesystem $files
    ) {
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
        $path = $this->laravel['config']['view.compiled'];

        if (! $path) {
            throw new RuntimeException('View path not found.');
        }

        $this->laravel['view.engine.resolver']
            ->resolve('blade')
            ->forgetCompiledOrNotExpired();

        foreach ($this->files->glob("{$path}/*") as $view) {
            if ($this->files->isDirectory($view)) {
                $this->files->deleteDirectory($view);
            } else {
                $this->files->delete($view);
            }
        }

        $this->components->info('Compiled views cleared successfully.');
    }
}
