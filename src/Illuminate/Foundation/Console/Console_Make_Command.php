<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Concerns\Creates_Matching_Test;
use Illuminate\Console\Generator_Command;
use Illuminate\Support\Stringable;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:command')]
class Console_Make_Command extends Generator_Command
{
    use Creates_Matching_Test;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:command';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Artisan command';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Console command';
    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     */
    protected function replace_class($stub, $name): string
    {
        $stub = parent::replace_class($stub, $name);
        $command = $this->option('command') ?: 'app:' . (new Stringable($name))->class_basename()->kebab()->value();
        return str_replace(['dummy:command', '{{ command }}'], $command, $stub);
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        $relative_path = '/stubs/console.stub';
        return file_exists($custom_path = $this->laravel->base_path(trim($relative_path, '/'))) ? $custom_path : __DIR__ . $relative_path;
    }
    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function get_default_namespace($root_namespace): string
    {
        return $root_namespace . '\Console\Commands';
    }
    /**
     * Get the console command arguments.
     */
    protected function get_arguments(): array
    {
        return [['name', Input_Argument::REQUIRED, 'The name of the command']];
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the console command already exists'], ['command', null, Input_Option::VALUE_OPTIONAL, 'The terminal command that will be used to invoke the class']];
    }
}