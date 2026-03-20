<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use Illuminate\Support\Service_Provider;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:provider')]
class Provider_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:provider';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new service provider class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Provider';
    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        $result = parent::handle();
        if ($result === false) {
            return $result;
        }
        Service_Provider::add_provider_to_bootstrap_file($this->qualify_class($this->get_name_input()), $this->laravel->get_bootstrap_providers_path());
        return $result;
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return $this->resolve_stub_path('/stubs/provider.stub');
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
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function get_default_namespace($root_namespace): string
    {
        return $root_namespace . '\Providers';
    }
    /**
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the provider already exists']];
    }
}