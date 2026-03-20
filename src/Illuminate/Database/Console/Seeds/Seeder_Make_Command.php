<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Seeds;

use Illuminate\Console\Generator_Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'make:seeder')]
class Seeder_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:seeder';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new seeder class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Seeder';
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        parent::handle();
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return $this->resolve_stub_path('/stubs/seeder.stub');
    }
    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @return string
     */
    protected function resolve_stub_path(string $stub)
    {
        return is_file($custom_path = $this->laravel->base_path(trim($stub, '/'))) ? $custom_path : __DIR__ . $stub;
    }
    /**
     * Get the destination class path.
     *
     * @param  string  $name
     */
    protected function get_path($name): string
    {
        $name = str_replace('\\', '/', Str::replace_first($this->root_namespace(), '', $name));
        if (is_dir($this->laravel->database_path() . '/seeds')) {
            return $this->laravel->database_path() . '/seeds/' . $name . '.php';
        }
        return $this->laravel->database_path() . '/seeders/' . $name . '.php';
    }
    /**
     * Get the root namespace for the class.
     */
    protected function root_namespace(): string
    {
        return 'Database\Seeders\\';
    }
}