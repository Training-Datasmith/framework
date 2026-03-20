<?php

declare (strict_types=1);
namespace Illuminate\Console\Concerns;

use Closure;
use Illuminate\Contracts\Console\Prompts_For_Missing_Input as PromptsForMissingInputContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use function Laravel\Prompts\text;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
trait Prompts_For_Missing_Input
{
    /**
     * Interact with the user before validating the input.
     *
     * @return void
     */
    protected function interact(Input_Interface $input, Output_Interface $output)
    {
        parent::interact($input, $output);
        if ($this instanceof Prompts_For_Missing_Input_Contract) {
            $this->prompt_for_missing_arguments($input, $output);
        }
    }
    /**
     * Prompt the user for any missing arguments.
     *
     * @return void
     */
    protected function prompt_for_missing_arguments(Input_Interface $input, Output_Interface $output)
    {
        $prompted = (new Collection($this->get_definition()->get_arguments()))->reject(fn(Input_Argument $argument): bool => $argument->get_name() === 'command')->filter(fn(Input_Argument $argument): bool => $argument->is_required() && match (true) {
            $argument->is_array() => empty($input->get_argument($argument->get_name())),
            default => is_null($input->get_argument($argument->get_name())),
        })->each(function (Input_Argument $argument) use ($input) {
            $label = $this->prompt_for_missing_arguments_using()[$argument->get_name()] ?? 'What is ' . lcfirst($argument->get_description() ?: 'the ' . $argument->get_name()) . '?';
            if ($label instanceof Closure) {
                return $input->set_argument($argument->get_name(), $argument->is_array() ? Arr::wrap($label()) : $label());
            }
            if (is_array($label)) {
                [$label, $placeholder] = $label;
            }
            $answer = text(label: $label, placeholder: $placeholder ?? '', validate: fn($value) => empty($value) ? "The {$argument->get_name()} is required." : null);
            $input->set_argument($argument->get_name(), $argument->is_array() ? [$answer] : $answer);
        })->is_not_empty();
        if ($prompted) {
            $this->after_prompting_for_missing_arguments($input, $output);
        }
    }
    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array<string, string|array{string, string}|\Closure(): (array<int|string>|string|int|bool)>
     */
    protected function prompt_for_missing_arguments_using(): array
    {
        return [];
    }
    /**
     * Perform actions after the user was prompted for missing arguments.
     *
     * @return void
     */
    protected function after_prompting_for_missing_arguments(Input_Interface $input, Output_Interface $output)
    {
    }
    /**
     * Whether the input contains any options that differ from the default values.
     */
    protected function did_receive_options(Input_Interface $input): bool
    {
        return (new Collection($this->get_definition()->get_options()))->reject(fn($option): bool => $input->get_option($option->get_name()) === $option->get_default())->is_not_empty();
    }
}