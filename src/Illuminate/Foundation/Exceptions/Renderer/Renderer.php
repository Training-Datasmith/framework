<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Exceptions\Renderer;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Symfony\Component\Error_Handler\Error_Renderer\Html_Error_Renderer;
use Throwable;
class Renderer
{
    /**
     * The path to the renderer's distribution files.
     *
     * @var string
     */
    protected const DIST = __DIR__ . '/../../resources/exceptions/renderer/dist/';
    /**
     * The HTML error renderer instance.
     *
     * @var \Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer
     */
    protected $html_error_renderer;
    /**
     * Creates a new exception renderer instance.
     */
    public function __construct(
        /**
         * The view factory instance.
         */
        protected \Illuminate\Contracts\View\Factory $view_factory,
        /**
         * The exception listener instance.
         */
        protected \Illuminate\Foundation\Exceptions\Renderer\Listener $listener,
        Html_Error_Renderer $html_error_renderer,
        /**
         * The Blade mapper instance.
         */
        protected \Illuminate\Foundation\Exceptions\Renderer\Mappers\Blade_Mapper $blade_mapper,
        /**
         * The application's base path.
         */
        protected string $base_path
    )
    {
        $this->html_error_renderer = $html_error_renderer;
    }
    /**
     * Render the given exception as an HTML string.
     *
     * @return string
     */
    public function render(Request $request, Throwable $throwable)
    {
        $flatten_exception = $this->blade_mapper->map($this->html_error_renderer->render($throwable));
        $exception = new Exception($flatten_exception, $request, $this->listener, $this->base_path);
        $exception_as_markdown = $this->view_factory->make('laravel-exceptions-renderer::markdown', ['exception' => $exception])->render();
        return $this->view_factory->make('laravel-exceptions-renderer::show', ['exception' => $exception, 'exceptionAsMarkdown' => $exception_as_markdown])->render();
    }
    /**
     * Get the renderer's CSS content.
     */
    public static function css(): string
    {
        return '<style>' . file_get_contents(static::DIST . 'styles.css') . '</style>';
    }
    /**
     * Get the renderer's JavaScript content.
     */
    public static function js(): string
    {
        $vite_js_auto_refresh = '';
        $vite = app(\Illuminate\Foundation\Vite::class);
        if (is_file($vite->hot_file())) {
            $vite_js_auto_refresh = $vite->__invoke([]);
        }
        return '<script>' . file_get_contents(static::DIST . 'scripts.js') . '</script>' . $vite_js_auto_refresh;
    }
}