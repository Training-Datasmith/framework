<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Foundation\Events\Maintenance_Mode_Disabled;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'up')]
class Up_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'up';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bring the application out of maintenance mode';
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            if (!$this->laravel->maintenance_mode()->active()) {
                $this->components->info('Application is already up.');
                return 0;
            }
            $this->laravel->maintenance_mode()->deactivate();
            if (is_file(storage_path('framework/maintenance.php'))) {
                unlink(storage_path('framework/maintenance.php'));
            }
            $this->laravel->get('events')->dispatch(new Maintenance_Mode_Disabled());
            $this->components->info('Application is now live.');
        } catch (Exception $e) {
            $this->components->error(sprintf('Failed to disable maintenance mode: %s.', $e->get_message()));
            return 1;
        }
        return 0;
    }
}