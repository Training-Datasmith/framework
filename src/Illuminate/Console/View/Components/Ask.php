<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components;

use Symfony\Component\Console\Question\Question;
class Ask extends Component
{
    /**
     * Renders the component using the given arguments.
     *
     * @param  string  $question
     * @param  string|null  $default
     * @return mixed
     */
    public function render($question, $default = null, bool $multiline = false)
    {
        return $this->using_question_helper(fn() => $this->output->ask_question((new Question($question, $default))->set_multiline($multiline)));
    }
}