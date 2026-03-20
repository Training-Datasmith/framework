<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'storage:link')]
class Storage_Link_Command extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'storage:link
                {--relative : Create the symbolic link using relative paths}
                {--force : Recreate existing symbolic links}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the symbolic links configured for the application';
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $relative = $this->option('relative');
        foreach ($this->links() as $link => $target) {
            if (file_exists($link) && !$this->is_removable_symlink($link, $this->option('force'))) {
                $this->components->error("The [{$link}] link already exists.");
                continue;
            }
            if (is_link($link)) {
                $this->laravel->make('files')->delete($link);
            }
            if ($relative) {
                $this->laravel->make('files')->relative_link($target, $link);
            } else {
                $this->laravel->make('files')->link($target, $link);
            }
            $this->components->info("The [{$link}] link has been connected to [{$target}].");
        }
    }
    /**
     * Get the symbolic links that are configured for the application.
     *
     * @return array
     */
    protected function links()
    {
        return $this->laravel['config']['filesystems.links'] ?? [public_path('storage') => storage_path('app/public')];
    }
    /**
     * Determine if the provided path is a symlink that can be removed.
     */
    protected function is_removable_symlink(string $link, bool $force): bool
    {
        return is_link($link) && $force;
    }
}