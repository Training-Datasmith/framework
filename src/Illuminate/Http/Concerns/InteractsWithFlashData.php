<?php

declare (strict_types=1);
namespace Illuminate\Http\Concerns;

use Illuminate\Database\Eloquent\Model;
trait Interacts_With_Flash_Data
{
    /**
     * Retrieve an old input item.
     *
     * @param  string|null  $key
     * @param  \Illuminate\Database\Eloquent\Model|string|array|null  $default
     * @return string|array|null
     */
    public function old($key = null, $default = null)
    {
        $default = $default instanceof Model ? $default->get_attribute($key) : $default;
        return $this->has_session() ? $this->session()->get_old_input($key, $default) : $default;
    }
    /**
     * Flash the input for the current request to the session.
     */
    public function flash(): void
    {
        $this->session()->flash_input($this->input());
    }
    /**
     * Flash only some of the input to the session.
     *
     * @param  mixed  $keys
     */
    public function flash_only($keys): void
    {
        $this->session()->flash_input($this->only(is_array($keys) ? $keys : func_get_args()));
    }
    /**
     * Flash only some of the input to the session.
     *
     * @param  mixed  $keys
     */
    public function flash_except($keys): void
    {
        $this->session()->flash_input($this->except(is_array($keys) ? $keys : func_get_args()));
    }
    /**
     * Flush all of the old input from the session.
     */
    public function flush(): void
    {
        $this->session()->flash_input([]);
    }
}