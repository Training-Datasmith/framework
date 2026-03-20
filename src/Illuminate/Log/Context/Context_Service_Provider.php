<?php

declare (strict_types=1);
namespace Illuminate\Log\Context;

use Illuminate\Contracts\Log\Context_Log_Processor as ContextLogProcessorContract;
use Illuminate\Queue\Events\Job_Processing;
use Illuminate\Queue\Queue;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Service_Provider;
class Context_Service_Provider extends Service_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->scoped(Repository::class);
        if ($this->app->running_in_console()) {
            $this->app->resolving(Repository::class, function (Repository $repository): void {
                $context = Env::get('__LARAVEL_CONTEXT');
                if ($context && $context = json_decode($context, associative: true)) {
                    $repository->hydrate($context);
                }
            });
        }
        $this->app->bind(Context_Log_Processor_Contract::class, fn(): \Illuminate\Log\Context\Context_Log_Processor => new Context_Log_Processor());
    }
    /**
     * Boot the application services.
     */
    public function boot(): void
    {
        Queue::create_payload_using(function ($connection, $queue, $payload) {
            /** @phpstan-ignore staticMethod.notFound */
            $context = Context::dehydrate();
            return $context === null ? $payload : [...$payload, 'illuminate:log:context' => $context];
        });
        $this->app['events']->listen(function (Job_Processing $event): void {
            /** @phpstan-ignore staticMethod.notFound */
            Context::hydrate($event->job->payload()['illuminate:log:context'] ?? null);
        });
    }
}