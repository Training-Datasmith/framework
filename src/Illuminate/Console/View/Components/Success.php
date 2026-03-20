<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components;

use Symfony\Component\Console\Output\Output_Interface;
class Success extends Component
{
    /**
     * Renders the component using the given arguments.
     *
     * @param  string  $string
     * @param  int  $verbosity
     */
    public function render($string, $verbosity = Output_Interface::VERBOSITY_NORMAL): void
    {
        (new Line($this->output))->render('success', $string, $verbosity);
    }
}