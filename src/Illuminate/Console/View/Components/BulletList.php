<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components;

use Symfony\Component\Console\Output\Output_Interface;
class Bullet_List extends Component
{
    /**
     * Renders the component using the given arguments.
     *
     * @param  array<int, string>  $elements
     * @param  int  $verbosity
     */
    public function render($elements, $verbosity = Output_Interface::VERBOSITY_NORMAL): void
    {
        $elements = $this->mutate($elements, [Mutators\Ensure_Dynamic_Content_Is_Highlighted::class, Mutators\Ensure_No_Punctuation::class, Mutators\Ensure_Relative_Paths::class]);
        $this->render_view('bullet-list', ['elements' => $elements], $verbosity);
    }
}