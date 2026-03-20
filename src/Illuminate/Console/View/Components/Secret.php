<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components;

use Symfony\Component\Console\Question\Question;
class Secret extends Component
{
    /**
     * Renders the component using the given arguments.
     *
     * @param  string  $question
     * @return mixed
     */
    public function render($question, bool $fallback = true)
    {
        $question = new Question($question);
        $question->set_hidden(true)->set_hidden_fallback($fallback);
        return $this->using_question_helper(fn() => $this->output->ask_question($question));
    }
}