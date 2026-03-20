<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components;

use Symfony\Component\Console\Output\Output_Interface;
class Two_Column_Detail extends Component
{
    /**
     * Renders the component using the given arguments.
     *
     * @param  string  $first
     * @param  string|null  $second
     * @param  int  $verbosity
     */
    public function render($first, $second = null, $verbosity = Output_Interface::VERBOSITY_NORMAL): void
    {
        $first = $this->mutate($first, [Mutators\Ensure_Dynamic_Content_Is_Highlighted::class, Mutators\Ensure_No_Punctuation::class, Mutators\Ensure_Relative_Paths::class]);
        $second = $this->mutate($second, [Mutators\Ensure_Dynamic_Content_Is_Highlighted::class, Mutators\Ensure_Relative_Paths::class]);
        $this->render_view('two-column-detail', ['first' => $first, 'second' => $second], $verbosity);
    }
}