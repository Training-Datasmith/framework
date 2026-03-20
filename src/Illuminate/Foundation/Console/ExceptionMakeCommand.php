<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use function Laravel\Prompts\confirm;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
#[As_Command(name: 'make:exception')]
class Exception_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:exception';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new custom exception class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Exception';
    /**
     * Get the stub file for the generator.
     */
    protected function get_stub(): string
    {
        if ($this->option('render')) {
            return $this->option('report') ? __DIR__ . '/stubs/exception-render-report.stub' : __DIR__ . '/stubs/exception-render.stub';
        }
        return $this->option('report') ? __DIR__ . '/stubs/exception-report.stub' : __DIR__ . '/stubs/exception.stub';
    }
    /**
     * Determine if the class already exists.
     *
     * @param  string  $rawName
     */
    protected function already_exists($raw_name): bool
    {
        return class_exists($this->root_namespace() . 'Exceptions\\' . $raw_name);
    }
    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function get_default_namespace($root_namespace): string
    {
        return $root_namespace . '\Exceptions';
    }
    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @return void
     */
    protected function after_prompting_for_missing_arguments(Input_Interface $input, Output_Interface $output)
    {
        if ($this->did_receive_options($input)) {
            return;
        }
        $input->set_option('report', confirm('Should the exception have a report method?', default: false));
        $input->set_option('render', confirm('Should the exception have a render method?', default: false));
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the exception already exists'], ['render', null, Input_Option::VALUE_NONE, 'Create the exception with an empty render method'], ['report', null, Input_Option::VALUE_NONE, 'Create the exception with an empty report method']];
    }
}