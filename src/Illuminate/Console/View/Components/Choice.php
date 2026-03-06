<?php

declare(strict_types=1);

namespace Illuminate\Console\View\Components;

use Symfony\Component\Console\Question\ChoiceQuestion;

class Choice extends Component
{
    /**
     * Renders the component using the given arguments.
     *
     * @param  string  $question
     * @param  array<array-key, string>  $choices
     * @param  mixed  $default
     * @return mixed
     */
    public function render($question, $choices, $default = null, ?int $attempts = null, bool $multiple = false)
    {
        return $this->usingQuestionHelper(
            fn () => $this->output->askQuestion(
                $this->getChoiceQuestion($question, $choices, $default)
                    ->setMaxAttempts($attempts)
                    ->setMultiselect($multiple)
            ),
        );
    }

    /**
     * Get a ChoiceQuestion instance that handles array keys like Prompts.
     *
     * @param  string  $question
     * @param  array  $choices
     * @param  mixed  $default
     */
    protected function getChoiceQuestion($question, $choices, $default): \Symfony\Component\Console\Question\ChoiceQuestion
    {
        return new class ($question, $choices, $default) extends ChoiceQuestion {
            protected function isAssoc(array $array): bool
            {
                return ! array_is_list($array);
            }
        };
    }
}
