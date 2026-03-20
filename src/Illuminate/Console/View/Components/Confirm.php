<?php

declare (strict_types=1);
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
        return $this->using_question_helper(fn() => $this->output->confirm($question, $default));
    }
}