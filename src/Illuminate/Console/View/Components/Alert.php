<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components;

use Symfony\Component\Console\Output\Output_Interface;
class Alert extends Component
{
    /**
     * Renders the component using the given arguments.
     *
     * @param  string  $string
     * @param  int  $verbosity
     */
    public function render($string, $verbosity = Output_Interface::VERBOSITY_NORMAL): void
    {
        $string = $this->mutate($string, [Mutators\Ensure_Dynamic_Content_Is_Highlighted::class, Mutators\Ensure_Punctuation::class, Mutators\Ensure_Relative_Paths::class]);
        $this->render_view('alert', ['content' => $string], $verbosity);
    }
}