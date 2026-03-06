<?php

namespace Illuminate\Console\View\Components;

class Confirm extends Component
{
    /**
     * Renders the component using the given arguments.
     *
     * @return bool
     */
    public function render(string $question, bool $default = false)
    {
        return $this->usingQuestionHelper(
            fn () => $this->output->confirm($question, $default),
        );
    }
}
