<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components;

use Illuminate\Console\Output_Style;
use Illuminate\Console\Question_Helper;
use ReflectionClass;
use Symfony\Component\Console\Helper\Symfony_Question_Helper;
use function Termwind\render;
use function Termwind\Render_Using;
abstract class Component
{
    /**
     * The list of mutators to apply on the view data.
     *
     * @var array<int, callable(string): string>
     */
    protected $mutators;
    /**
     * Creates a new component instance.
     *
     * @param  \Illuminate\Console\OutputStyle  $output
     */
    public function __construct(
        /**
         * The output style implementation.
         */
        protected $output
    )
    {
    }
    /**
     * Renders the given view.
     *
     * @param  string  $view
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $data
     * @param  int  $verbosity
     * @return void
     */
    protected function render_view($view, $data, $verbosity)
    {
        render_using($this->output);
        render((string) $this->compile($view, $data), $verbosity);
    }
    /**
     * Compile the given view contents.
     *
     * @param  string  $view
     * @param  array  $data
     * @return string
     */
    protected function compile($view, $data)
    {
        extract($data);
        ob_start();
        include __DIR__ . "/../../resources/views/components/{$view}.php";
        return tap(ob_get_contents(), function (): void {
            ob_end_clean();
        });
    }
    /**
     * Mutates the given data with the given set of mutators.
     *
     * @param  array<int, string>|string  $data
     * @param  array<int, callable(string): string>  $mutators
     * @return array<int, string>|string
     */
    protected function mutate($data, $mutators)
    {
        foreach ($mutators as $mutator) {
            $mutator = new $mutator();
            if (is_iterable($data)) {
                foreach ($data as $key => $value) {
                    $data[$key] = $mutator($value);
                }
            } else {
                $data = $mutator($data);
            }
        }
        return $data;
    }
    /**
     * Eventually performs a question using the component's question helper.
     *
     * @param  callable  $callable
     * @return mixed
     */
    protected function using_question_helper($callable)
    {
        $property = (new ReflectionClass(Output_Style::class))->get_parent_class()->get_property('questionHelper');
        $current_helper = $property->is_initialized($this->output) ? $property->get_value($this->output) : new Symfony_Question_Helper();
        $property->set_value($this->output, new Question_Helper());
        try {
            return $callable();
        } finally {
            $property->set_value($this->output, $current_helper);
        }
    }
}