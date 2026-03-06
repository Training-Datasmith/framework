<?php

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

        $question->setHidden(true)->setHiddenFallback($fallback);

        return $this->usingQuestionHelper(fn () => $this->output->askQuestion($question));
    }
}
