<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components;

use Illuminate\Console\View\Task_Result;
use Illuminate\Support\Interacts_With_Time;
use Symfony\Component\Console\Output\Output_Interface;
use function Termwind\terminal;
use Throwable;
class Task extends Component
{
    use Interacts_With_Time;
    /**
     * Renders the component using the given arguments.
     *
     * @param  string  $description
     * @param  (callable(): bool)|null  $task
     */
    public function render($description, $task = null, int $verbosity = Output_Interface::VERBOSITY_NORMAL): void
    {
        $description = $this->mutate($description, [Mutators\Ensure_Dynamic_Content_Is_Highlighted::class, Mutators\Ensure_No_Punctuation::class, Mutators\Ensure_Relative_Paths::class]);
        $description_width = mb_strlen(preg_replace("/\\<[\\w=#\\/\\;,:.&,%?]+\\>|\\e\\[\\d+m/", '$1', $description) ?? '');
        $this->output->write("  {$description} ", false, $verbosity);
        $start_time = microtime(true);
        $result = Task_Result::Failure->value;
        try {
            $result = ($task ?: fn() => Task_Result::Success->value)();
        } catch (Throwable $e) {
            throw $e;
        } finally {
            $run_time = $task ? ' ' . $this->run_time_for_humans($start_time) : '';
            $run_time_width = mb_strlen($run_time);
            $width = min(terminal()->width(), 150);
            $dots = max($width - $description_width - $run_time_width - 10, 0);
            $this->output->write(str_repeat('<fg=gray>.</>', $dots), false, $verbosity);
            $this->output->write("<fg=gray>{$run_time}</>", false, $verbosity);
            $this->output->writeln(match ($result) {
                Task_Result::Failure->value => ' <fg=red;options=bold>FAIL</>',
                Task_Result::Skipped->value => ' <fg=yellow;options=bold>SKIPPED</>',
                default => ' <fg=green;options=bold>DONE</>',
            }, $verbosity);
        }
    }
}