<?php

declare (strict_types=1);
namespace Illuminate\Console;

use Illuminate\Console\Contracts\New_Line_Aware;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\Symfony_Style;
class Output_Style extends Symfony_Style implements New_Line_Aware
{
    /**
     * The number of trailing new lines written by the last output.
     *
     * This is initialized as 1 to account for the new line written by the shell after executing a command.
     *
     * @var int
     */
    protected $new_lines_written = 1;
    /**
     * If the last output written wrote a new line.
     *
     * @var bool
     *
     * @deprecated use $newLinesWritten
     */
    protected $new_line_written = false;
    /**
     * Create a new Console OutputStyle instance.
     */
    public function __construct(
        Input_Interface $input,
        /**
         * The output instance.
         */
        private readonly Output_Interface $output
    )
    {
        parent::__construct($input, $this->output);
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function ask_question(Question $question): mixed
    {
        try {
            return parent::ask_question($question);
        } finally {
            $this->new_lines_written++;
        }
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        $this->new_lines_written = $this->trailing_new_line_count($messages) + (int) $newline;
        $this->new_line_written = $this->new_lines_written > 0;
        parent::write($messages, $newline, $options);
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function writeln(string|iterable $messages, int $type = self::OUTPUT_NORMAL): void
    {
        if ($this->output->get_verbosity() >= $type) {
            $this->new_lines_written = $this->trailing_new_line_count($messages) + 1;
            $this->new_line_written = true;
        }
        parent::writeln($messages, $type);
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function new_line(int $count = 1): void
    {
        $this->new_lines_written += $count;
        $this->new_line_written = $this->new_lines_written > 0;
        parent::new_line($count);
    }
    /**
     * {@inheritdoc}
     */
    public function new_lines_written()
    {
        if ($this->output instanceof static) {
            return $this->output->new_lines_written();
        }
        return $this->new_lines_written;
    }
    /**
     * {@inheritdoc}
     *
     * @deprecated use newLinesWritten
     */
    public function new_line_written()
    {
        if ($this->output instanceof static && $this->output->new_line_written()) {
            return true;
        }
        return $this->new_line_written;
    }
    /*
     * Count the number of trailing new lines in a string.
     *
     * @param  string|iterable<string>  $messages
     * @return int
     */
    protected function trailing_new_line_count($messages): int
    {
        if (is_iterable($messages)) {
            $string = '';
            foreach ($messages as $message) {
                $string .= $message . PHP_EOL;
            }
        } else {
            $string = $messages;
        }
        return strlen((string) $string) - strlen(rtrim((string) $string, PHP_EOL));
    }
    /**
     * Returns whether verbosity is quiet (-q).
     */
    public function is_quiet(): bool
    {
        return $this->output->is_quiet();
    }
    /**
     * Returns whether verbosity is verbose (-v).
     */
    public function is_verbose(): bool
    {
        return $this->output->is_verbose();
    }
    /**
     * Returns whether verbosity is very verbose (-vv).
     */
    public function is_very_verbose(): bool
    {
        return $this->output->is_very_verbose();
    }
    /**
     * Returns whether verbosity is debug (-vvv).
     */
    public function is_debug(): bool
    {
        return $this->output->is_debug();
    }
    /**
     * Get the underlying Symfony output implementation.
     */
    public function get_output(): \Symfony\Component\Console\Output\Output_Interface
    {
        return $this->output;
    }
}