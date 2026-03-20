<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Concerns\Creates_Matching_Test;
use Illuminate\Console\Generator_Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
#[As_Command(name: 'make:notification')]
class Notification_Make_Command extends Generator_Command
{
    use Creates_Matching_Test;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:notification';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new notification class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Notification';
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (parent::handle() === false && !$this->option('force')) {
            return;
        }
        if ($this->option('markdown')) {
            $this->write_markdown_template();
        }
    }
    /**
     * Write the Markdown template for the mailable.
     *
     * @return void
     */
    protected function write_markdown_template()
    {
        $separator = '/';
        if (windows_os()) {
            $separator = '\\';
        }
        $path = $this->view_path(str_replace('.', $separator, $this->option('markdown')) . '.blade.php');
        if (!$this->files->is_directory(dirname($path))) {
            $this->files->make_directory(dirname($path), 0755, true);
        }
        $this->files->put($path, file_get_contents(__DIR__ . '/stubs/markdown.stub'));
        $this->components->info(sprintf('%s [%s] created successfully.', 'Markdown', $path));
    }
    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function build_class($name)
    {
        $class = parent::build_class($name);
        if ($this->option('markdown')) {
            return str_replace(['DummyView', '{{ view }}'], $this->option('markdown'), $class);
        }
        return $class;
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return $this->option('markdown') ? $this->resolve_stub_path('/stubs/markdown-notification.stub') : $this->resolve_stub_path('/stubs/notification.stub');
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
        return $root_namespace . '\Notifications';
    }
    /**
     * Perform actions after the user was prompted for missing arguments.
     *
     * @return void
     */
    protected function after_prompting_for_missing_arguments(Input_Interface $input, Output_Interface $output)
    {
        if ($this->did_receive_options($input)) {
            return;
        }
        $wants_markdown_view = confirm('Would you like to create a markdown view?');
        if ($wants_markdown_view) {
            $default_markdown_view = (new Collection(explode('/', str_replace('\\', '/', $this->argument('name')))))->map(fn($path) => Str::kebab($path))->prepend('mail')->implode('.');
            $markdown_view = text('What should the markdown view be named?', default: $default_markdown_view);
            $input->set_option('markdown', $markdown_view);
        }
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the notification already exists'], ['markdown', 'm', Input_Option::VALUE_OPTIONAL, 'Create a new Markdown template for the notification']];
    }
}