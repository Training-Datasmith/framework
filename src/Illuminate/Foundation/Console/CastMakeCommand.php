<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:cast')]
class CastMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:cast';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new custom Eloquent cast class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Cast';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->option('inbound')
            ? $this->resolveStubPath('/stubs/cast.inbound.stub')
            : $this->resolveStubPath('/stubs/cast.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @return string
     */
    protected function resolveStubPath(string $stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Casts';
    }

    /**
     * Get the console command arguments.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the cast already exists'],
            ['inbound', null, InputOption::VALUE_NONE, 'Generate an inbound cast class'],
        ];
    }
}
