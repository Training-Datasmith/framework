<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Support\Providers\Event_Service_Provider;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'event:generate')]
class Event_Generate_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'event:generate';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the missing events and listeners based on registration';
    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $providers = $this->laravel->get_providers(Event_Service_Provider::class);
        foreach ($providers as $provider) {
            foreach ($provider->listens() as $event => $listeners) {
                $this->make_event_and_listeners($event, $listeners);
            }
        }
        $this->components->info('Events and listeners generated successfully.');
    }
    /**
     * Make the event and listeners for the given event.
     *
     * @param  string  $event
     * @param  array  $listeners
     * @return void
     */
    protected function make_event_and_listeners($event, $listeners)
    {
        if (!str_contains($event, '\\')) {
            return;
        }
        $this->call_silent('make:event', ['name' => $event]);
        $this->make_listeners($event, $listeners);
    }
    /**
     * Make the listeners for the given event.
     *
     * @param  string  $event
     * @param  array  $listeners
     * @return void
     */
    protected function make_listeners($event, $listeners)
    {
        foreach ($listeners as $listener) {
            $listener = preg_replace('/@.+$/', '', (string) $listener);
            $this->call_silent('make:listener', array_filter(['name' => $listener, '--event' => $event]));
        }
    }
}