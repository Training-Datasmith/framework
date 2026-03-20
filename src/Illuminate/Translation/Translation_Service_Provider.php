<?php

declare(strict_types=1);

namespace Illuminate\Translation;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->registerLoader();

        $this->app->singleton('translator', function (array $app): \Illuminate\Translation\Translator {
            $loader = $app['translation.loader'];

            // When registering the translator component, we'll need to set the default
            // locale as well as the fallback locale. So, we'll grab the application
            // configuration so we can easily get both of these values from there.
            $locale = $app->getLocale();

            $trans = new Translator($loader, $locale);

            $trans->setFallback($app->getFallbackLocale());

            return $trans;
        });
    }

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader()
    {
        $this->app->singleton('translation.loader', fn ($app): \Illuminate\Translation\FileLoader => new FileLoader($app['files'], [__DIR__.'/lang', $app['path.lang']]));
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['translator', 'translation.loader'];
    }
}
