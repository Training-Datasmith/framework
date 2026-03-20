<?php

declare (strict_types=1);
namespace Illuminate\Console\Concerns;

use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Option;
trait Has_Parameters
{
    /**
     * Specify the arguments and options on the command.
     *
     * @return void
     */
    protected function specify_parameters()
    {
        // We will loop through all of the arguments and options for the command and
        // set them all on the base command instance. This specifies what can get
        // passed into these commands as "parameters" to control the execution.
        foreach ($this->get_arguments() as $arguments) {
            if ($arguments instanceof Input_Argument) {
                $this->get_definition()->add_argument($arguments);
            } else {
                $this->add_argument(...$arguments);
            }
        }
        foreach ($this->get_options() as $options) {
            if ($options instanceof Input_Option) {
                $this->get_definition()->add_option($options);
            } else {
                $this->add_option(...$options);
            }
        }
    }
    /**
     * Get the console command arguments.
     *
     * @return (InputArgument|array{
     *    0: non-empty-string,
     *    1?: InputArgument::REQUIRED|InputArgument::OPTIONAL|InputArgument::IS_ARRAY,
     *    2?: string,
     *    3?: mixed,
     *    4?: list<string|Suggestion>|\Closure(CompletionInput, CompletionSuggestions): list<string|Suggestion>
     * })[]
     */
    protected function get_arguments(): array
    {
        return [];
    }
    /**
     * Get the console command options.
     *
     * @return (InputOption|array{
     *    0: non-empty-string,
     *    1?: string|non-empty-array<string>,
     *    2?: InputOption::VALUE_*,
     *    3?: string,
     *    4?: mixed,
     *    5?: list<string|Suggestion>|\Closure(CompletionInput, CompletionSuggestions): list<string|Suggestion>
     * })[]
     */
    protected function get_options(): array
    {
        return [];
    }
}