<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

use Exception;
trait Without_Middleware
{
    /**
     * Prevent all middleware from being executed for this test class.
     *
     * @throws \Exception
     */
    public function disable_middleware_for_all_tests(): void
    {
        if (method_exists($this, 'withoutMiddleware')) {
            $this->without_middleware();
        } else {
            throw new Exception('Unable to disable middleware. MakesHttpRequests trait not used.');
        }
    }
}