<?php

declare(strict_types=1);

namespace Illuminate\Mail;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->registerIlluminateMailer();
        $this->registerMarkdownRenderer();
    }

    /**
     * Register the Illuminate mailer instance.
     *
     * @return void
     */
    protected function registerIlluminateMailer()
    {
        $this->app->singleton('mail.manager', fn ($app): \Illuminate\Mail\MailManager => new MailManager($app));

        $this->app->bind('mailer', fn ($app) => $app->make('mail.manager')->mailer());
    }

    /**
     * Register the Markdown renderer instance.
     *
     * @return void
     */
    protected function registerMarkdownRenderer()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/resources/views' => $this->app->resourcePath('views/vendor/mail'),
            ], 'laravel-mail');
        }

        $this->app->singleton(Markdown::class, function ($app): \Illuminate\Mail\Markdown {
            $config = $app->make('config');

            return new Markdown($app->make('view'), [
                'theme' => $config->get('mail.markdown.theme', 'default'),
                'paths' => $config->get('mail.markdown.paths', []),
            ]);
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'mail.manager',
            'mailer',
            Markdown::class,
        ];
    }
}
