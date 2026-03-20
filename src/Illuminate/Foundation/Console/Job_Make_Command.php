<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Concerns\Creates_Matching_Test;
use Illuminate\Console\Generator_Command;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:job')]
class Job_Make_Command extends Generator_Command
{
    use Creates_Matching_Test;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:job';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new job class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Job';
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        if ($this->option('batched')) {
            return $this->resolve_stub_path('/stubs/job.batched.queued.stub');
        }
        return $this->option('sync') ? $this->resolve_stub_path('/stubs/job.stub') : $this->resolve_stub_path('/stubs/job.queued.stub');
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
        return $root_namespace . '\Jobs';
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the job already exists'], ['sync', null, Input_Option::VALUE_NONE, 'Indicates that the job should be synchronous'], ['batched', null, Input_Option::VALUE_NONE, 'Indicates that the job should be batchable']];
    }
}