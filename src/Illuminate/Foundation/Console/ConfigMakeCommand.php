<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use function Illuminate\Filesystem\join_paths;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:config', aliases: ['config:make'])]
class Config_Make_Command extends Generator_Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:config';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new configuration file';
    /**
     * The type of file being generated.
     *
     * @var string
     */
    protected $type = 'Config';
    /**
     * The console command name aliases.
     *
     * @var array<int, string>
     */
    protected $aliases = ['config:make'];
    /**
     * Get the destination file path.
     *
     * @param  string  $name
     */
    protected function get_path($name): string
    {
        return config_path(Str::finish($this->argument('name'), '.php'));
    }
    /**
     * Get the stub file for the generator.
     */
    protected function get_stub(): string
    {
        $relative_path = join_paths('stubs', 'config.stub');
        return file_exists($custom_path = $this->laravel->base_path($relative_path)) ? $custom_path : join_paths(__DIR__, $relative_path);
    }
    /**
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the configuration file even if it already exists']];
    }
    /**
     * Prompt for missing input arguments using the returned questions.
     */
    protected function prompt_for_missing_arguments_using(): array
    {
        return ['name' => 'What should the configuration file be named?'];
    }
}