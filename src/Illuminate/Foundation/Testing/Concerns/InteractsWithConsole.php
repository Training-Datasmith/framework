<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

use Illuminate\Console\Output_Style;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Testing\Pending_Command;
trait Interacts_With_Console
{
    /**
     * Indicates if the console output should be mocked.
     *
     * @var bool
     */
    public $mock_console_output = true;
    /**
     * Indicates if the command is expected to output anything.
     *
     * @var bool|null
     */
    public $expects_output;
    /**
     * All of the expected output lines.
     *
     * @var array
     */
    public $expected_output = [];
    /**
     * All of the expected text to be present in the output.
     *
     * @var array
     */
    public $expected_output_substrings = [];
    /**
     * All of the output lines that aren't expected to be displayed.
     *
     * @var array
     */
    public $unexpected_output = [];
    /**
     * All of the text that is not expected to be present in the output.
     *
     * @var array
     */
    public $unexpected_output_substrings = [];
    /**
     * All of the expected output tables.
     *
     * @var array
     */
    public $expected_tables = [];
    /**
     * All of the expected questions.
     *
     * @var array
     */
    public $expected_questions = [];
    /**
     * All of the expected choice questions.
     *
     * @var array
     */
    public $expected_choices = [];
    /**
     * Call artisan command and return code.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return \Illuminate\Testing\PendingCommand|int
     */
    public function artisan($command, $parameters = [])
    {
        if (!$this->mock_console_output) {
            return $this->app[Kernel::class]->call($command, $parameters);
        }
        return new Pending_Command($this, $this->app, $command, $parameters);
    }
    /**
     * Disable mocking the console output.
     *
     * @return $this
     */
    protected function without_mocking_console_output()
    {
        $this->mock_console_output = false;
        $this->app->offsetUnset(Output_Style::class);
        return $this;
    }
}