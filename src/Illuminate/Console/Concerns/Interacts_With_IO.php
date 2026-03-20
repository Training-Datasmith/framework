<?php

declare (strict_types=1);
namespace Illuminate\Console\Concerns;

use Closure;
use Illuminate\Console\Output_Style;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\Output_Formatter_Style;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Question\Choice_Question;
use Symfony\Component\Console\Question\Question;
trait Interacts_With_Io
{
    /**
     * The console components factory.
     *
     * @var \Illuminate\Console\View\Components\Factory
     */
    protected $components;
    /**
     * The input interface implementation.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;
    /**
     * The output interface implementation.
     *
     * @var \Illuminate\Console\OutputStyle
     */
    protected $output;
    /**
     * The default verbosity of output commands.
     *
     * @var \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*
     */
    protected $verbosity = Output_Interface::VERBOSITY_NORMAL;
    /**
     * The mapping between human-readable verbosity levels and Symfony's OutputInterface.
     *
     * @var array<string, \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*>
     */
    protected $verbosity_map = ['v' => Output_Interface::VERBOSITY_VERBOSE, 'vv' => Output_Interface::VERBOSITY_VERY_VERBOSE, 'vvv' => Output_Interface::VERBOSITY_DEBUG, 'quiet' => Output_Interface::VERBOSITY_QUIET, 'normal' => Output_Interface::VERBOSITY_NORMAL];
    /**
     * Determine if the given argument is present.
     *
     * @param  string|int  $name
     * @return bool
     */
    public function has_argument($name)
    {
        return $this->input->has_argument($name);
    }
    /**
     * Get the value of a command argument.
     *
     * @param  string|null  $key
     * @return ($key is null ? array<array|string|float|int|bool|null> : array|string|float|int|bool|null)
     */
    public function argument($key = null)
    {
        if (is_null($key)) {
            return $this->input->get_arguments();
        }
        return $this->input->get_argument($key);
    }
    /**
     * Get all of the arguments passed to the command.
     *
     * @return array<array|string|float|int|bool|null>
     */
    public function arguments()
    {
        return $this->argument();
    }
    /**
     * Determine whether the option is defined in the command signature.
     *
     * @param  string  $name
     * @return bool
     */
    public function has_option($name)
    {
        return $this->input->has_option($name);
    }
    /**
     * Get the value of a command option.
     *
     * @param  string|null  $key
     * @return ($key is null ? array<array|string|float|int|bool|null> : array|string|float|int|bool|null)
     */
    public function option($key = null)
    {
        if (is_null($key)) {
            return $this->input->get_options();
        }
        return $this->input->get_option($key);
    }
    /**
     * Get all of the options passed to the command.
     *
     * @return array<array|string|float|int|bool|null>
     */
    public function options()
    {
        return $this->option();
    }
    /**
     * Confirm a question with the user.
     *
     * @param  string  $question
     * @param  bool  $default
     * @return bool
     */
    public function confirm($question, $default = false)
    {
        return $this->output->confirm($question, $default);
    }
    /**
     * Prompt the user for input.
     *
     * @param  string  $question
     * @param  string|null  $default
     * @return mixed
     */
    public function ask($question, $default = null)
    {
        return $this->output->ask($question, $default);
    }
    /**
     * Prompt the user for input with auto completion.
     *
     * @param  string  $question
     * @param  array|callable  $choices
     * @param  string|null  $default
     * @return mixed
     */
    public function anticipate($question, $choices, $default = null)
    {
        return $this->ask_with_completion($question, $choices, $default);
    }
    /**
     * Prompt the user for input with auto completion.
     *
     * @param  string  $question
     * @param  iterable|(callable(string): string[])  $choices
     * @param  string|null  $default
     * @return mixed
     */
    public function ask_with_completion($question, $choices, $default = null)
    {
        $question = new Question($question, $default);
        is_callable($choices) ? $question->set_autocompleter_callback($choices) : $question->set_autocompleter_values($choices);
        return $this->output->ask_question($question);
    }
    /**
     * Prompt the user for input but hide the answer from the console.
     *
     * @param  string  $question
     * @return mixed
     */
    public function secret($question, bool $fallback = true)
    {
        $question = new Question($question);
        $question->set_hidden(true)->set_hidden_fallback($fallback);
        return $this->output->ask_question($question);
    }
    /**
     * Give the user a single choice from an array of answers.
     *
     * @param  string  $question
     * @param  array<\Stringable|string|float|int|bool>  $choices
     * @param  string|int|null  $default
     * @param  ?positive-int  $attempts
     * @return string|array
     */
    public function choice($question, array $choices, $default = null, ?int $attempts = null, bool $multiple = false)
    {
        $question = new Choice_Question($question, $choices, $default);
        $question->set_max_attempts($attempts)->set_multiselect($multiple);
        return $this->output->ask_question($question);
    }
    /**
     * Format input to textual table.
     *
     * @param  array  $headers
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $rows
     * @param  array<int, \Symfony\Component\Console\Helper\TableStyle|string>  $columnStyles
     */
    public function table($headers, $rows, \Symfony\Component\Console\Helper\Table_Style|string $table_style = 'default', array $column_styles = []): void
    {
        $table = new Table($this->output);
        if ($rows instanceof Arrayable) {
            $rows = $rows->to_array();
        }
        $table->set_headers((array) $headers)->set_rows($rows)->set_style($table_style);
        foreach ($column_styles as $column_index => $column_style) {
            $table->set_column_style($column_index, $column_style);
        }
        $table->render();
    }
    /**
     * Execute a given callback while advancing a progress bar.
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param  iterable<TKey, TValue>|int  $totalSteps
     * @param  \Closure(\Symfony\Component\Console\Helper\ProgressBar|TValue, \Symfony\Component\Console\Helper\ProgressBar|null, TKey|null): void  $callback
     * @return mixed|void
     */
    public function with_progress_bar($total_steps, Closure $callback)
    {
        $bar = $this->output->create_progress_bar(is_iterable($total_steps) ? count($total_steps) : $total_steps);
        $bar->start();
        if (is_iterable($total_steps)) {
            foreach ($total_steps as $key => $value) {
                $callback($value, $bar, $key);
                $bar->advance();
            }
        } else {
            $callback($bar);
        }
        $bar->finish();
        if (is_iterable($total_steps)) {
            return $total_steps;
        }
    }
    /**
     * Write a string as information output.
     *
     * @param  string  $string
     * @param  'v'|'vv'|'vvv'|'quiet'|'normal'|\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*|null  $verbosity
     */
    public function info($string, $verbosity = null): void
    {
        $this->line($string, 'info', $verbosity);
    }
    /**
     * Write a string as standard output.
     *
     * @param  string  $string
     * @param  'info'|'comment'|'question'|'error'|'warn'|'alert'|null  $style
     * @param  'v'|'vv'|'vvv'|'quiet'|'normal'|\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*|null  $verbosity
     */
    public function line($string, $style = null, $verbosity = null): void
    {
        $styled = $style ? "<{$style}>{$string}</{$style}>" : $string;
        $this->output->writeln($styled, $this->parse_verbosity($verbosity));
    }
    /**
     * Write a string as comment output.
     *
     * @param  string  $string
     * @param  'v'|'vv'|'vvv'|'quiet'|'normal'|\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*|null  $verbosity
     */
    public function comment($string, $verbosity = null): void
    {
        $this->line($string, 'comment', $verbosity);
    }
    /**
     * Write a string as question output.
     *
     * @param  string  $string
     * @param  'v'|'vv'|'vvv'|'quiet'|'normal'|\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*|null  $verbosity
     */
    public function question($string, $verbosity = null): void
    {
        $this->line($string, 'question', $verbosity);
    }
    /**
     * Write a string as error output.
     *
     * @param  string  $string
     * @param  'v'|'vv'|'vvv'|'quiet'|'normal'|\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*|null  $verbosity
     */
    public function error($string, $verbosity = null): void
    {
        $this->line($string, 'error', $verbosity);
    }
    /**
     * Write a string as warning output.
     *
     * @param  string  $string
     * @param  'v'|'vv'|'vvv'|'quiet'|'normal'|\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*|null  $verbosity
     */
    public function warn($string, $verbosity = null): void
    {
        if (!$this->output->get_formatter()->has_style('warning')) {
            $style = new Output_Formatter_Style('yellow');
            $this->output->get_formatter()->set_style('warning', $style);
        }
        $this->line($string, 'warning', $verbosity);
    }
    /**
     * Write a string in an alert box.
     *
     * @param  'v'|'vv'|'vvv'|'quiet'|'normal'|\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*|null  $verbosity
     */
    public function alert(string $string, $verbosity = null): void
    {
        $length = Str::length(strip_tags($string)) + 12;
        $this->comment(str_repeat('*', $length), $verbosity);
        $this->comment('*     ' . $string . '     *', $verbosity);
        $this->comment(str_repeat('*', $length), $verbosity);
        $this->comment('', $verbosity);
    }
    /**
     * Write a blank line.
     *
     * @param  int  $count
     * @return $this
     */
    public function new_line($count = 1)
    {
        $this->output->new_line($count);
        return $this;
    }
    /**
     * Set the input interface implementation.
     */
    public function set_input(Input_Interface $input): void
    {
        $this->input = $input;
    }
    /**
     * Set the output interface implementation.
     */
    public function set_output(Output_Style $output): void
    {
        $this->output = $output;
    }
    /**
     * Set the verbosity level.
     *
     * @param  'v'|'vv'|'vvv'|'quiet'|'normal'|\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*  $level
     * @return void
     */
    protected function set_verbosity($level)
    {
        $this->verbosity = $this->parse_verbosity($level);
    }
    /**
     * Get the verbosity level in terms of Symfony's OutputInterface level.
     *
     * @param  'v'|'vv'|'vvv'|'quiet'|'normal'|\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*|null  $level
     * @return int
     */
    protected function parse_verbosity($level = null)
    {
        $level ??= '';
        if (isset($this->verbosity_map[$level])) {
            $level = $this->verbosity_map[$level];
        } elseif (!is_int($level)) {
            $level = $this->verbosity;
        }
        return $level;
    }
    /**
     * Get the output implementation.
     *
     * @return \Illuminate\Console\OutputStyle
     */
    public function get_output()
    {
        return $this->output;
    }
    /**
     * Get the output component factory implementation.
     *
     * @return \Illuminate\Console\View\Components\Factory
     */
    public function output_components()
    {
        return $this->components;
    }
}