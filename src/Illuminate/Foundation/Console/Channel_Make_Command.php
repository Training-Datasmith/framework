<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:channel')]
class Channel_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:channel';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new channel class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Channel';
    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     */
    protected function build_class($name): string
    {
        return str_replace(['DummyUser', '{{ userModel }}'], class_basename($this->user_provider_model()), parent::build_class($name));
    }
    /**
     * Get the stub file for the generator.
     */
    protected function get_stub(): string
    {
        return __DIR__ . '/stubs/channel.stub';
    }
    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function get_default_namespace($root_namespace): string
    {
        return $root_namespace . '\Broadcasting';
    }
    /**
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the channel already exists']];
    }
}