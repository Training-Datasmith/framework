<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components;

use Illuminate\Console\Contracts\New_Line_Aware;
use Symfony\Component\Console\Output\Output_Interface;
class Line extends Component
{
    /**
     * The possible line styles.
     *
     * @var array<string, array<string, string>>
     */
    protected static $styles = ['info' => ['bgColor' => 'blue', 'fgColor' => 'white', 'title' => 'info'], 'success' => ['bgColor' => 'green', 'fgColor' => 'white', 'title' => 'success'], 'warn' => ['bgColor' => 'yellow', 'fgColor' => 'black', 'title' => 'warn'], 'error' => ['bgColor' => 'red', 'fgColor' => 'white', 'title' => 'error']];
    /**
     * Renders the component using the given arguments.
     *
     * @param  string  $string
     * @param  int  $verbosity
     */
    public function render(string $style, $string, $verbosity = Output_Interface::VERBOSITY_NORMAL): void
    {
        $string = $this->mutate($string, [Mutators\Ensure_Dynamic_Content_Is_Highlighted::class, Mutators\Ensure_Punctuation::class, Mutators\Ensure_Relative_Paths::class]);
        $this->render_view('line', array_merge(static::$styles[$style], ['marginTop' => $this->output instanceof New_Line_Aware ? max(0, 2 - $this->output->new_lines_written()) : 1, 'content' => $string]), $verbosity);
    }
}