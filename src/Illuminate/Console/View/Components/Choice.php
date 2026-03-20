<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components;

use Symfony\Component\Console\Question\Choice_Question;
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
        return $this->using_question_helper(fn() => $this->output->ask_question($this->get_choice_question($question, $choices, $default)->set_max_attempts($attempts)->set_multiselect($multiple)));
    }
    /**
     * Get a ChoiceQuestion instance that handles array keys like Prompts.
     *
     * @param  string  $question
     * @param  array  $choices
     * @param  mixed  $default
     */
    protected function get_choice_question($question, $choices, $default): \Symfony\Component\Console\Question\Choice_Question
    {
        return new class($question, $choices, $default) extends Choice_Question
        {
            protected function is_assoc(array $array): bool
            {
                return !array_is_list($array);
            }
        };
    }
}