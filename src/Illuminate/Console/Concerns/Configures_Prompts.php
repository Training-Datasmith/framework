<?php

declare (strict_types=1);
namespace Illuminate\Console\Concerns;

use Illuminate\Console\Prompt_Validation_Exception;
use Laravel\Prompts\Confirm_Prompt;
use Laravel\Prompts\Multi_Search_Prompt;
use Laravel\Prompts\Multi_Select_Prompt;
use Laravel\Prompts\Password_Prompt;
use Laravel\Prompts\Pause_Prompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Search_Prompt;
use Laravel\Prompts\Select_Prompt;
use Laravel\Prompts\Suggest_Prompt;
use Laravel\Prompts\Textarea_Prompt;
use Laravel\Prompts\Text_Prompt;
use stdClass;
use Symfony\Component\Console\Input\Input_Interface;
trait Configures_Prompts
{
    /**
     * Configure the prompt fallbacks.
     *
     * @return void
     */
    protected function configure_prompts(Input_Interface $input)
    {
        Prompt::set_output($this->output);
        Prompt::interactive($input->is_interactive() && defined('STDIN') && stream_isatty(STDIN) || $this->laravel->running_unit_tests());
        Prompt::validate_using(fn(Prompt $prompt) => $this->validate_prompt($prompt->value(), $prompt->validate));
        Prompt::fallback_when(windows_os() || $this->laravel->running_unit_tests());
        Text_Prompt::fallback_using(fn(Text_Prompt $prompt) => $this->prompt_until_valid(fn() => $this->components->ask($prompt->label, $prompt->default ?: null) ?? '', $prompt->required, $prompt->validate));
        Textarea_Prompt::fallback_using(fn(Textarea_Prompt $prompt) => $this->prompt_until_valid(fn() => $this->components->ask($prompt->label, $prompt->default ?: null, multiline: true) ?? '', $prompt->required, $prompt->validate));
        Password_Prompt::fallback_using(fn(Password_Prompt $prompt) => $this->prompt_until_valid(fn() => $this->components->secret($prompt->label) ?? '', $prompt->required, $prompt->validate));
        Pause_Prompt::fallback_using(fn(Pause_Prompt $prompt) => $this->prompt_until_valid(function () use ($prompt) {
            $this->components->ask($prompt->message, $prompt->value());
            return $prompt->value();
        }, $prompt->required, $prompt->validate));
        Confirm_Prompt::fallback_using(fn(Confirm_Prompt $prompt) => $this->prompt_until_valid(fn() => $this->components->confirm($prompt->label, $prompt->default), $prompt->required, $prompt->validate));
        Select_Prompt::fallback_using(fn(Select_Prompt $prompt) => $this->prompt_until_valid(fn() => $this->select_fallback($prompt->label, $prompt->options, $prompt->default), false, $prompt->validate));
        Multi_Select_Prompt::fallback_using(fn(Multi_Select_Prompt $prompt) => $this->prompt_until_valid(fn() => $this->multiselect_fallback($prompt->label, $prompt->options, $prompt->default, $prompt->required), $prompt->required, $prompt->validate));
        Suggest_Prompt::fallback_using(fn(Suggest_Prompt $prompt) => $this->prompt_until_valid(fn() => $this->components->ask_with_completion($prompt->label, $prompt->options, $prompt->default ?: null) ?? '', $prompt->required, $prompt->validate));
        Search_Prompt::fallback_using(fn(Search_Prompt $prompt) => $this->prompt_until_valid(function () use ($prompt) {
            $query = $this->components->ask($prompt->label);
            $options = ($prompt->options)($query);
            return $this->select_fallback($prompt->label, $options);
        }, false, $prompt->validate));
        Multi_Search_Prompt::fallback_using(fn(Multi_Search_Prompt $prompt) => $this->prompt_until_valid(function () use ($prompt) {
            $query = $this->components->ask($prompt->label);
            $options = ($prompt->options)($query);
            return $this->multiselect_fallback($prompt->label, $options, required: $prompt->required);
        }, $prompt->required, $prompt->validate));
    }
    /**
     * Prompt the user until the given validation callback passes.
     *
     * @template PResult
     *
     * @param  \Closure(): PResult  $prompt
     * @param  bool|string  $required
     * @param  (\Closure(PResult): mixed)|null  $validate
     * @return PResult
     */
    protected function prompt_until_valid($prompt, $required, $validate)
    {
        while (true) {
            $result = $prompt();
            if ($required && ($result === '' || $result === [] || $result === false)) {
                $this->components->error(is_string($required) ? $required : 'Required.');
                if ($this->laravel->running_unit_tests()) {
                    throw new Prompt_Validation_Exception();
                }
                continue;
            }
            $error = is_callable($validate) ? $validate($result) : $this->validate_prompt($result, $validate);
            if (is_string($error) && strlen($error) > 0) {
                $this->components->error($error);
                if ($this->laravel->running_unit_tests()) {
                    throw new Prompt_Validation_Exception();
                }
                continue;
            }
            return $result;
        }
    }
    /**
     * Validate the given prompt value using the validator.
     *
     * @param  mixed  $value
     * @param  mixed  $rules
     * @return ?string
     */
    protected function validate_prompt($value, $rules)
    {
        if ($rules instanceof stdClass) {
            $messages = $rules->messages ?? [];
            $attributes = $rules->attributes ?? [];
            $rules = $rules->rules ?? null;
        }
        if (!$rules) {
            return;
        }
        $field = 'answer';
        if (is_array($rules) && !array_is_list($rules)) {
            [$field, $rules] = [key($rules), current($rules)];
        }
        return $this->get_prompt_validator_instance($field, $value, $rules, $messages ?? [], $attributes ?? [])->errors()->first();
    }
    /**
     * Get the validator instance that should be used to validate prompts.
     *
     * @param  mixed  $field
     * @param  mixed  $value
     * @param  mixed  $rules
     * @return \Illuminate\Validation\Validator
     */
    protected function get_prompt_validator_instance($field, $value, $rules, array $messages = [], array $attributes = [])
    {
        return $this->laravel['validator']->make([$field => $value], [$field => $rules], empty($messages) ? $this->validation_messages() : $messages, empty($attributes) ? $this->validation_attributes() : $attributes);
    }
    /**
     * Get the validation messages that should be used during prompt validation.
     *
     * @return array<string, string>
     */
    protected function validation_messages(): array
    {
        return [];
    }
    /**
     * Get the validation attributes that should be used during prompt validation.
     *
     * @return array<string, string>
     */
    protected function validation_attributes(): array
    {
        return [];
    }
    /**
     * Restore the prompts output.
     *
     * @return void
     */
    protected function restore_prompts()
    {
        Prompt::set_output($this->output);
    }
    /**
     * Select fallback.
     *
     * @param  string  $label
     * @param  array<array-key, string>  $options
     * @param  string|int|null  $default
     * @return string|int
     */
    private function select_fallback($label, $options, $default = null)
    {
        $answer = $this->components->choice($label, $options, $default);
        if (!array_is_list($options) && $answer === (string) (int) $answer) {
            return (int) $answer;
        }
        return $answer;
    }
    /**
     * Multi-select fallback.
     *
     * @param  string  $label
     * @param  array  $options
     * @param  array  $default
     * @param  bool|string  $required
     * @return array
     */
    private function multiselect_fallback($label, $options, $default = [], $required = false)
    {
        $default = $default !== [] ? implode(',', $default) : null;
        if ($required === false && !$this->laravel->running_unit_tests()) {
            $options = array_is_list($options) ? ['None', ...$options] : ['' => 'None'] + $options;
            if ($default === null) {
                $default = 'None';
            }
        }
        $answers = $this->components->choice($label, $options, $default, null, true);
        if (!array_is_list($options)) {
            $answers = array_map(fn($value) => $value === (string) (int) $value ? (int) $value : $value, $answers);
        }
        if ($required === false) {
            return array_is_list($options) ? array_values(array_filter($answers, fn($value): bool => $value !== 'None')) : array_filter($answers, fn($value): bool => $value !== '');
        }
        return $answers;
    }
}