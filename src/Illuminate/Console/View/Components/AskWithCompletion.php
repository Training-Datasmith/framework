<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components;

use Symfony\Component\Console\Question\Question;
class Ask_With_Completion extends Component
{
    /**
     * Renders the component using the given arguments.
     *
     * @param  string  $question
     * @param  array|callable  $choices
     * @param  string|null  $default
     * @return mixed
     */
    public function render($question, $choices, $default = null)
    {
        $question = new Question($question, $default);
        is_callable($choices) ? $question->set_autocompleter_callback($choices) : $question->set_autocompleter_values($choices);
        return $this->using_question_helper(fn() => $this->output->ask_question($question));
    }
}