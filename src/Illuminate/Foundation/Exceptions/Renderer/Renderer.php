<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Exceptions\Renderer;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Throwable;

class Renderer
{
    /**
     * The path to the renderer's distribution files.
     *
     * @var string
     */
    protected const DIST = __DIR__.'/../../resources/exceptions/renderer/dist/';

    /**
     * The HTML error renderer instance.
     *
     * @var \Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer
     */
    protected $htmlErrorRenderer;

    /**
     * Creates a new exception renderer instance.
     */
    public function __construct(
        /**
         * The view factory instance.
         */
        protected \Illuminate\Contracts\View\Factory $viewFactory,
        /**
         * The exception listener instance.
         */
        protected \Illuminate\Foundation\Exceptions\Renderer\Listener $listener,
        HtmlErrorRenderer $htmlErrorRenderer,
        /**
         * The Blade mapper instance.
         */
        protected \Illuminate\Foundation\Exceptions\Renderer\Mappers\BladeMapper $bladeMapper,
        /**
         * The application's base path.
         */
        protected string $basePath,
    ) {
        $this->htmlErrorRenderer = $htmlErrorRenderer;
    }

    /**
     * Render the given exception as an HTML string.
     *
     * @return string
     */
    public function render(Request $request, Throwable $throwable)
    {
        $flattenException = $this->bladeMapper->map(
            $this->htmlErrorRenderer->render($throwable),
        );

        $exception = new Exception($flattenException, $request, $this->listener, $this->basePath);

        $exceptionAsMarkdown = $this->viewFactory->make('laravel-exceptions-renderer::markdown', [
            'exception' => $exception,
        ])->render();

        return $this->viewFactory->make('laravel-exceptions-renderer::show', [
            'exception' => $exception,
            'exceptionAsMarkdown' => $exceptionAsMarkdown,
        ])->render();
    }

    /**
     * Get the renderer's CSS content.
     */
    public static function css(): string
    {
        return '<style>'.file_get_contents(static::DIST.'styles.css').'</style>';
    }

    /**
     * Get the renderer's JavaScript content.
     */
    public static function js(): string
    {
        $viteJsAutoRefresh = '';

        $vite = app(\Illuminate\Foundation\Vite::class);

        if (is_file($vite->hotFile())) {
            $viteJsAutoRefresh = $vite->__invoke([]);
        }

        return '<script>'
            .file_get_contents(static::DIST.'scripts.js')
            .'</script>'.$viteJsAutoRefresh;
    }
}
