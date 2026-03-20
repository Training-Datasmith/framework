<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\Message_Bag;
use Illuminate\Support\View_Error_Bag;
use Illuminate\Testing\Test_Component;
use Illuminate\Testing\Test_View;
use Illuminate\View\View;
trait Interacts_With_Views
{
    /**
     * Create a new TestView from the given view.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $data
     */
    protected function view(string $view, $data = []): \Illuminate\Testing\Test_View
    {
        return new Test_View(view($view, $data));
    }
    /**
     * Render the contents of the given Blade template string.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $data
     */
    protected function blade(string $template, $data = []): \Illuminate\Testing\Test_View
    {
        $temp_directory = sys_get_temp_dir();
        if (!in_array($temp_directory, View_Facade::get_finder()->get_paths())) {
            View_Facade::add_location(sys_get_temp_dir());
        }
        $temp_file_info = pathinfo(tempnam($temp_directory, 'laravel-blade'));
        $temp_file = $temp_file_info['dirname'] . '/' . $temp_file_info['filename'] . '.blade.php';
        file_put_contents($temp_file, $template);
        return new Test_View(view($temp_file_info['filename'], $data));
    }
    /**
     * Render the given view component.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $data
     */
    protected function component(string $component_class, $data = []): \Illuminate\Testing\Test_Component
    {
        $component = $this->app->make($component_class, $data);
        $view = value($component->resolve_view(), $data);
        $view = $view instanceof View ? $view->with($component->data()) : view($view, $component->data());
        return new Test_Component($component, $view);
    }
    /**
     * Populate the shared view error bag with the given errors.
     *
     * @return $this
     */
    protected function with_view_errors(array $errors, string $key = 'default')
    {
        View_Facade::share('errors', (new View_Error_Bag())->put($key, new Message_Bag($errors)));
        return $this;
    }
}