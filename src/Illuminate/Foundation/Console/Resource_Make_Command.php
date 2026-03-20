<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:resource')]
class Resource_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:resource';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new resource';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Resource';
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->collection()) {
            $this->type = 'Resource collection';
        }
        parent::handle();
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return match (true) {
            $this->collection() => $this->resolve_stub_path('/stubs/resource-collection.stub'),
            $this->option('json-api') => $this->resolve_stub_path('/stubs/resource-json-api.stub'),
            default => $this->resolve_stub_path('/stubs/resource.stub'),
        };
    }
    /**
     * Determine if the command is generating a resource collection.
     */
    protected function collection(): bool
    {
        if ($this->option('collection')) {
            return true;
        }
        return str_ends_with($this->argument('name'), 'Collection');
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
        return $root_namespace . '\Http\Resources';
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the resource already exists'], ['json-api', 'j', Input_Option::VALUE_NONE, 'Create a JSON:API resource'], ['collection', 'c', Input_Option::VALUE_NONE, 'Create a resource collection']];
    }
}