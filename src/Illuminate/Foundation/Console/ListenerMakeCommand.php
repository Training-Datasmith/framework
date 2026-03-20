<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Concerns\Creates_Matching_Test;
use Illuminate\Console\Generator_Command;
use Illuminate\Support\Str;
use function Laravel\Prompts\suggest;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
#[As_Command(name: 'make:listener')]
class Listener_Make_Command extends Generator_Command
{
    use Creates_Matching_Test;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:listener';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new event listener class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Listener';
    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     */
    protected function build_class($name): string
    {
        $event = $this->option('event') ?? '';
        if (!Str::starts_with($event, [$this->laravel->get_namespace(), 'Illuminate', '\\'])) {
            $event = $this->laravel->get_namespace() . 'Events\\' . str_replace('/', '\\', $event);
        }
        $stub = str_replace(['DummyEvent', '{{ event }}'], class_basename($event), parent::build_class($name));
        return str_replace(['DummyFullEvent', '{{ eventNamespace }}'], trim($event, '\\'), $stub);
    }
    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @return string
     */
    protected function resolve_stub_path(string $stub)
    {
        return file_exists($custom_path = $this->laravel->base_path(trim($stub, '/'))) ? $custom_path : __DIR__ . $stub;
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        if ($this->option('queued')) {
            return $this->option('event') ? $this->resolve_stub_path('/stubs/listener.typed.queued.stub') : $this->resolve_stub_path('/stubs/listener.queued.stub');
        }
        return $this->option('event') ? $this->resolve_stub_path('/stubs/listener.typed.stub') : $this->resolve_stub_path('/stubs/listener.stub');
    }
    /**
     * Determine if the class already exists.
     *
     * @param  string  $rawName
     */
    protected function already_exists($raw_name): bool
    {
        return class_exists($this->qualify_class($raw_name));
    }
    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function get_default_namespace($root_namespace): string
    {
        return $root_namespace . '\Listeners';
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['event', 'e', Input_Option::VALUE_OPTIONAL, 'The event class being listened for'], ['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the listener already exists'], ['queued', null, Input_Option::VALUE_NONE, 'Indicates the event listener should be queued']];
    }
    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @return void
     */
    protected function after_prompting_for_missing_arguments(Input_Interface $input, Output_Interface $output)
    {
        if ($this->is_reserved_name($this->get_name_input()) || $this->did_receive_options($input)) {
            return;
        }
        $event = suggest('What event should be listened for? (Optional)', $this->possible_events());
        if ($event) {
            $input->set_option('event', $event);
        }
    }
}