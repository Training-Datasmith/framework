<?php

declare (strict_types=1);
namespace Illuminate\Console;

use Symfony\Component\Console\Output\Console_Output;
class Buffered_Console_Output extends Console_Output
{
    /**
     * The current buffer.
     *
     * @var string
     */
    protected $buffer = '';
    /**
     * Empties the buffer and returns its content.
     *
     * @return string
     */
    public function fetch()
    {
        return tap($this->buffer, function (): void {
            $this->buffer = '';
        });
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function do_write(string $message, bool $newline): void
    {
        $this->buffer .= $message;
        if ($newline) {
            $this->buffer .= \PHP_EOL;
        }
        parent::do_write($message, $newline);
    }
}