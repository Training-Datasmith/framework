<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:rule')]
class Rule_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:rule';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new validation rule';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Rule';
    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function build_class($name): string
    {
        return str_replace('{{ ruleType }}', $this->option('implicit') ? 'ImplicitRule' : 'Rule', parent::build_class($name));
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        $stub = $this->option('implicit') ? '/stubs/rule.implicit.stub' : '/stubs/rule.stub';
        return file_exists($custom_path = $this->laravel->base_path(trim($stub, '/'))) ? $custom_path : __DIR__ . $stub;
    }
    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function get_default_namespace($root_namespace): string
    {
        return $root_namespace . '\Rules';
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the rule already exists'], ['implicit', 'i', Input_Option::VALUE_NONE, 'Generate an implicit rule']];
    }
}