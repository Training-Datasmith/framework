<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Support\Providers\Event_Service_Provider;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'event:cache')]
class Event_Cache_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:cache';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Discover and cache the application's events and listeners";
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->call_silent('event:clear');
        file_put_contents($this->laravel->get_cached_events_path(), '<?php return ' . var_export($this->get_events(), true) . ';');
        $this->components->info('Events cached successfully.');
    }
    /**
     * Get all of the events and listeners configured for the application.
     */
    protected function get_events(): array
    {
        $events = [];
        foreach ($this->laravel->get_providers(Event_Service_Provider::class) as $provider) {
            $provider_events = array_merge_recursive($provider->should_discover_events() ? $provider->discover_events() : [], $provider->listens());
            $events[$provider::class] = $provider_events;
        }
        return $events;
    }
}