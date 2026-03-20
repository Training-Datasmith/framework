<?php

declare (strict_types=1);
namespace Illuminate\Console;

use Illuminate\Console\View\Components\Two_Column_Detail;
use Illuminate\Support\Stringable;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Helper\Symfony_Question_Helper;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Question\Choice_Question;
use Symfony\Component\Console\Question\Confirmation_Question;
use Symfony\Component\Console\Question\Question;
class Question_Helper extends Symfony_Question_Helper
{
    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function write_prompt(Output_Interface $output, Question $question): void
    {
        $text = Output_Formatter::escape_trailing_backslash($question->get_question());
        $text = $this->ensure_ends_with_punctuation($text);
        $text = "  <fg=default;options=bold>{$text}</></>";
        $default = $question->get_default();
        if ($question->is_multiline()) {
            $text .= sprintf(' (press %s to continue)', 'Windows' == PHP_OS_FAMILY ? '<comment>Ctrl+Z</comment> then <comment>Enter</comment>' : '<comment>Ctrl+D</comment>');
        }
        switch (true) {
            case null === $default:
                $text = sprintf('<info>%s</info>', $text);
                break;
            case $question instanceof Confirmation_Question:
                $text = sprintf('<info>%s (yes/no)</info> [<comment>%s</comment>]', $text, $default ? 'yes' : 'no');
                break;
            case $question instanceof Choice_Question:
                $choices = $question->get_choices();
                $text = sprintf('<info>%s</info> [<comment>%s</comment>]', $text, Output_Formatter::escape($choices[$default] ?? $default));
                break;
            default:
                $text = sprintf('<info>%s</info> [<comment>%s</comment>]', $text, Output_Formatter::escape($default));
                break;
        }
        $output->writeln($text);
        if ($question instanceof Choice_Question) {
            foreach ($question->get_choices() as $key => $value) {
                (new Two_Column_Detail($output))->render($value, $key);
            }
        }
        $output->write('<options=bold>❯ </>');
    }
    /**
     * Ensures the given string ends with punctuation.
     *
     * @param  string  $string
     * @return string
     */
    protected function ensure_ends_with_punctuation($string)
    {
        if (!(new Stringable($string))->ends_with(['?', ':', '!', '.'])) {
            return "{$string}:";
        }
        return $string;
    }
}