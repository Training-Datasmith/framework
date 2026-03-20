<?php

declare(strict_types=1);

namespace Illuminate\Support\Defer;

use Illuminate\Support\Str;

/**
 * A named, invokable callback that is deferred until after the response is sent.
 *
 * Deferred callbacks are executed at the end of the request/job lifecycle via
 * `defer()`. They can be given a name so that duplicate registrations can be
 * detected and cancelled, and can be configured to run even when the request
 * or job was not successful.
 *
 * @since 11.x
 */
class DeferredCallback
{
    /**
     * Create a new deferred callback instance.
     *
     * The name defaults to a UUID so every callback is uniquely identifiable
     * even when no explicit name is provided.
     *
     * @param  callable      $callback  The closure or callable to invoke after the response is sent.
     * @param  string|null   $name      Optional name used for deduplication and cancellation.
     *                                  Defaults to a UUID when null.
     * @param  bool          $always    Whether to run even on failed requests/jobs (default: false).
     */
    public function __construct(
        public readonly mixed $callback,
        public ?string $name = null,
        public bool $always = false
    ) {
        $this->name = $name ?? (string) Str::uuid();
    }

    /**
     * Specify the name of the deferred callback so it can be cancelled later.
     *
     * Naming a callback allows you to call `\Illuminate\Support\defer()->cancel($name)`
     * before the response is sent to prevent the callback from running.
     *
     * @param  string  $name  Unique identifier for this deferred callback.
     * @return $this
     *
     * @since 11.x
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Indicate that the deferred callback should run even on unsuccessful requests and jobs.
     *
     * By default, deferred callbacks are skipped when the request or job fails.
     * Use this when cleanup logic must run regardless of outcome (e.g. releasing locks).
     *
     * @param  bool  $always  Pass false to revert to the default "only on success" behaviour.
     * @return $this
     *
     * @since 11.x
     */
    public function always(bool $always = true): static
    {
        $this->always = $always;

        return $this;
    }

    /**
     * Invoke the deferred callback.
     *
     * Called by the framework after the response has been sent to the client.
     *
     * @since 11.x
     */
    public function __invoke(): void
    {
        call_user_func($this->callback);
    }
}
