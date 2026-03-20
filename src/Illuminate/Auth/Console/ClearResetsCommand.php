<?php

declare (strict_types=1);
namespace Illuminate\Auth\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'auth:clear-resets')]
class Clear_Resets_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:clear-resets {name? : The name of the password broker}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush expired password reset tokens';
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->laravel['auth.password']->broker($this->argument('name'))->get_repository()->delete_expired();
        $this->components->info('Expired reset tokens cleared successfully.');
    }
}