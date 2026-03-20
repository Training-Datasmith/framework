<?php

declare (strict_types=1);
namespace Illuminate\Console\Concerns;

use Illuminate\Support\Stringable;
use Symfony\Component\Console\Input\Input_Option;
trait Creates_Matching_Test
{
    /**
     * Add the standard command options for generating matching tests.
     *
     * @return void
     */
    protected function add_test_options()
    {
        foreach (['test' => 'Test', 'pest' => 'Pest', 'phpunit' => 'PHPUnit'] as $option => $name) {
            $this->get_definition()->add_option(new Input_Option($option, null, Input_Option::VALUE_NONE, "Generate an accompanying {$name} test for the {$this->type}"));
        }
    }
    /**
     * Create the matching test case if requested.
     *
     * @param  string  $path
     * @return bool
     */
    protected function handle_test_creation($path)
    {
        if (!$this->option('test') && !$this->option('pest') && !$this->option('phpunit')) {
            return false;
        }
        return $this->call('make:test', ['name' => (new Stringable($path))->after($this->laravel['path'])->before_last('.php')->append('Test')->replace('\\', '/'), '--pest' => $this->option('pest'), '--phpunit' => $this->option('phpunit'), '--force' => $this->has_option('force') && $this->option('force')]) == 0;
    }
}