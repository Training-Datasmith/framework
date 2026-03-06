<?php

declare(strict_types=1);

namespace Illuminate\Session;

use Illuminate\Support\InteractsWithTime;
use SessionHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

class CookieSessionHandler implements SessionHandlerInterface
{
    use InteractsWithTime;

    /**
     * The request instance.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * Create a new cookie driven handler instance.
     *
     * @param  int  $minutes
     * @param  bool  $expireOnClose
     */
    public function __construct(
        /**
         * The cookie jar instance.
         */
        protected \Illuminate\Contracts\Cookie\QueueingFactory $cookie,
        /**
         * The number of minutes the session should be valid.
         */
        protected $minutes,
        /**
         * Indicates whether the session should be expired when the browser closes.
         */
        protected $expireOnClose = false
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return string|false
     */
    public function read($sessionId): string|false
    {
        $value = $this->request->cookies->get($sessionId) ?: '';

        if (! is_null($decoded = json_decode((string) $value, true)) && is_array($decoded) &&
            isset($decoded['expires']) && $this->currentTime() <= $decoded['expires']) {
            return $decoded['data'];
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data): bool
    {
        $this->cookie->queue($sessionId, json_encode([
            'data' => $data,
            'expires' => $this->availableAt($this->minutes * 60),
        ]), $this->expireOnClose ? 0 : $this->minutes);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId): bool
    {
        $this->cookie->queue($this->cookie->forget($sessionId));

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($lifetime): int
    {
        return 0;
    }

    /**
     * Set the request instance.
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }
}
