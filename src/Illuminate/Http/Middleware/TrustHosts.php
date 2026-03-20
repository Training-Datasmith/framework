<?php

declare (strict_types=1);
namespace Illuminate\Http\Middleware;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
class Trust_Hosts
{
    /**
     * The trusted hosts that have been configured to always be trusted.
     *
     * @var array<int, string>|(callable(): array<int, string>)|null
     */
    protected static $always_trust;
    /**
     * Indicates whether subdomains of the application URL should be trusted.
     *
     * @var bool|null
     */
    protected static $subdomains;
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        /**
         * The application instance.
         */
        protected \Illuminate\Contracts\Foundation\Application $app
    )
    {
    }
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array
     */
    public function hosts()
    {
        if (is_null(static::$always_trust)) {
            return [$this->all_subdomains_of_application_url()];
        }
        $hosts = match (true) {
            is_array(static::$always_trust) => static::$always_trust,
            is_callable(static::$always_trust) => call_user_func(static::$always_trust),
            default => [],
        };
        if (static::$subdomains) {
            $hosts[] = $this->all_subdomains_of_application_url();
        }
        return $hosts;
    }
    /**
     * Handle the incoming request.
     *
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, $next)
    {
        if ($this->should_specify_trusted_hosts()) {
            Request::set_trusted_hosts(array_filter($this->hosts()));
        }
        return $next($request);
    }
    /**
     * Specify the hosts that should always be trusted.
     *
     * @param  array<int, string>|(callable(): array<int, string>)  $hosts
     */
    public static function at(array|callable $hosts, bool $subdomains = true): void
    {
        static::$always_trust = $hosts;
        static::$subdomains = $subdomains;
    }
    /**
     * Determine if the application should specify trusted hosts.
     */
    protected function should_specify_trusted_hosts(): bool
    {
        return !$this->app->environment('local') && !$this->app->running_unit_tests();
    }
    /**
     * Get a regular expression matching the application URL and all of its subdomains.
     *
     * @return string|null
     */
    protected function all_subdomains_of_application_url()
    {
        if ($host = parse_url((string) $this->app['config']->get('app.url'), PHP_URL_HOST)) {
            return '^(.+\.)?' . preg_quote($host) . '$';
        }
    }
    /**
     * Flush the state of the middleware.
     */
    public static function flush_state(): void
    {
        static::$always_trust = null;
        static::$subdomains = null;
    }
}