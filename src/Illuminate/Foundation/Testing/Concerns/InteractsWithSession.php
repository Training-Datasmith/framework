<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

trait Interacts_With_Session
{
    /**
     * Set the session to the given array.
     *
     * @return $this
     */
    public function with_session(array $data)
    {
        $this->session($data);
        return $this;
    }
    /**
     * Set the session to the given array.
     *
     * @return $this
     */
    public function session(array $data)
    {
        $this->start_session();
        foreach ($data as $key => $value) {
            $this->app['session']->put($key, $value);
        }
        return $this;
    }
    /**
     * Start the session for the application.
     *
     * @return $this
     */
    protected function start_session()
    {
        if (!$this->app['session']->is_started()) {
            $this->app['session']->start();
        }
        return $this;
    }
    /**
     * Flush all of the current session data.
     *
     * @return $this
     */
    public function flush_session()
    {
        $this->start_session();
        $this->app['session']->flush();
        return $this;
    }
}