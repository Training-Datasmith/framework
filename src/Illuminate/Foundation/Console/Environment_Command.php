<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'env')]
class Environment_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'env';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display the current framework environment';
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->components->info(sprintf('The application environment is [%s].', $this->laravel['env']));
    }
}