<?php

declare (strict_types=1);
namespace Illuminate\Cookie;

use Illuminate\Contracts\Cookie\Queueing_Factory as JarContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Interacts_With_Time;
use Illuminate\Support\Traits\Macroable;
use Symfony\Component\Http_Foundation\Cookie;
class Cookie_Jar implements Jar_Contract
{
    use Interacts_With_Time;
    use Macroable;
    /**
     * The default path (if specified).
     *
     * @var string
     */
    protected $path = '/';
    /**
     * The default domain (if specified).
     *
     * @var string|null
     */
    protected $domain;
    /**
     * The default secure setting (defaults to null).
     *
     * @var bool|null
     */
    protected $secure;
    /**
     * The default SameSite option (defaults to lax).
     *
     * @var string
     */
    protected $same_site = 'lax';
    /**
     * All of the cookies queued for sending.
     *
     * @var \Symfony\Component\HttpFoundation\Cookie[]
     */
    protected $queued = [];
    /**
     * Create a new cookie instance.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  int  $minutes
     * @param  string|null  $path
     * @param  string|null  $domain
     * @param  bool|null  $secure
     * @param  bool  $httpOnly
     * @param  bool  $raw
     * @param  string|null  $sameSite
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function make($name, $value, $minutes = 0, $path = null, $domain = null, $secure = null, $http_only = true, $raw = false, $same_site = null)
    {
        [$path, $domain, $secure, $same_site] = $this->get_path_and_domain($path, $domain, $secure, $same_site);
        $time = $minutes == 0 ? 0 : $this->available_at($minutes * 60);
        return new Cookie($name, $value, $time, $path, $domain, $secure, $http_only, $raw, $same_site);
    }
    /**
     * Create a cookie that lasts "forever" (400 days).
     *
     * @param  string  $name
     * @param  string  $value
     * @param  string|null  $path
     * @param  string|null  $domain
     * @param  bool|null  $secure
     * @param  bool  $httpOnly
     * @param  bool  $raw
     * @param  string|null  $sameSite
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function forever($name, $value, $path = null, $domain = null, $secure = null, $http_only = true, $raw = false, $same_site = null)
    {
        return $this->make($name, $value, 576000, $path, $domain, $secure, $http_only, $raw, $same_site);
    }
    /**
     * Expire the given cookie.
     *
     * @param  string  $name
     * @param  string|null  $path
     * @param  string|null  $domain
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function forget($name, $path = null, $domain = null)
    {
        return $this->make($name, null, -2628000, $path, $domain);
    }
    /**
     * Determine if a cookie has been queued.
     *
     * @param  string  $key
     * @param  string|null  $path
     */
    public function has_queued($key, $path = null): bool
    {
        return !is_null($this->queued($key, null, $path));
    }
    /**
     * Get a queued cookie instance.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @param  string|null  $path
     * @return \Symfony\Component\HttpFoundation\Cookie|null
     */
    public function queued($key, $default = null, $path = null)
    {
        $queued = Arr::get($this->queued, $key, $default);
        if ($path === null) {
            return Arr::last($queued, null, $default);
        }
        return Arr::get($queued, $path, $default);
    }
    /**
     * Queue a cookie to send with the next response.
     *
     * @param  mixed  ...$parameters
     */
    public function queue(...$parameters): void
    {
        if (isset($parameters[0]) && $parameters[0] instanceof Cookie) {
            $cookie = $parameters[0];
        } else {
            $cookie = $this->make(...array_values($parameters));
        }
        if (!isset($this->queued[$cookie->get_name()])) {
            $this->queued[$cookie->get_name()] = [];
        }
        $this->queued[$cookie->get_name()][$cookie->get_path()] = $cookie;
    }
    /**
     * Queue a cookie to expire with the next response.
     *
     * @param  string  $name
     * @param  string|null  $path
     * @param  string|null  $domain
     */
    public function expire($name, $path = null, $domain = null): void
    {
        $this->queue($this->forget($name, $path, $domain));
    }
    /**
     * Remove a cookie from the queue.
     *
     * @param  string  $name
     * @param  string|null  $path
     */
    public function unqueue($name, $path = null): void
    {
        if ($path === null) {
            unset($this->queued[$name]);
            return;
        }
        unset($this->queued[$name][$path]);
        if (empty($this->queued[$name])) {
            unset($this->queued[$name]);
        }
    }
    /**
     * Get the path and domain, or the default values.
     *
     * @param  string  $path
     * @param  string|null  $domain
     * @param  bool|null  $secure
     * @param  string|null  $sameSite
     */
    protected function get_path_and_domain($path, $domain, $secure = null, $same_site = null): array
    {
        return [$path ?: $this->path, $domain ?: $this->domain, is_bool($secure) ? $secure : $this->secure, $same_site ?: $this->same_site];
    }
    /**
     * Set the default path and domain for the jar.
     *
     * @param  string  $path
     * @param  string|null  $domain
     * @param  bool|null  $secure
     * @param  string|null  $sameSite
     * @return $this
     */
    public function set_default_path_and_domain($path, $domain, $secure = false, $same_site = null): static
    {
        [$this->path, $this->domain, $this->secure, $this->same_site] = [$path, $domain, $secure, $same_site];
        return $this;
    }
    /**
     * Get the cookies which have been queued for the next request.
     *
     * @return \Symfony\Component\HttpFoundation\Cookie[]
     */
    public function get_queued_cookies(): array
    {
        return Arr::flatten($this->queued);
    }
    /**
     * Flush the cookies which have been queued for the next request.
     *
     * @return $this
     */
    public function flush_queued_cookies(): static
    {
        $this->queued = [];
        return $this;
    }
}