<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

use Closure;
use Illuminate\Foundation\Mix;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Defer\Deferred_Callback_Collection;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Illuminate\Support\Html_String;
use Mockery;
trait Interacts_With_Container
{
    /**
     * The original Vite handler.
     *
     * @var \Illuminate\Foundation\Vite|null
     */
    protected $original_vite;
    /**
     * The original Laravel Mix handler.
     *
     * @var \Illuminate\Foundation\Mix|null
     */
    protected $original_mix;
    /**
     * The original deferred callbacks collection.
     *
     * @var \Illuminate\Support\Defer\DeferredCallbackCollection|null
     */
    protected $original_deferred_callbacks_collection;
    /**
     * Register an instance of an object in the container.
     *
     * @template TSwap of object
     *
     * @param  string  $abstract
     * @param  TSwap  $instance
     * @return TSwap
     */
    protected function swap($abstract, $instance)
    {
        return $this->instance($abstract, $instance);
    }
    /**
     * Register an instance of an object in the container.
     *
     * @template TInstance of object
     *
     * @param  string  $abstract
     * @param  TInstance  $instance
     * @return TInstance
     */
    protected function instance($abstract, $instance)
    {
        $this->app->instance($abstract, $instance);
        return $instance;
    }
    /**
     * Mock an instance of an object in the container.
     *
     * @param  string  $abstract
     * @return \Mockery\MockInterface
     */
    protected function mock($abstract, ?Closure $mock = null)
    {
        return $this->instance($abstract, Mockery::mock(...array_filter(func_get_args())));
    }
    /**
     * Mock a partial instance of an object in the container.
     *
     * @param  string  $abstract
     * @return \Mockery\MockInterface
     */
    protected function partial_mock($abstract, ?Closure $mock = null)
    {
        return $this->instance($abstract, Mockery::mock(...array_filter(func_get_args()))->make_partial());
    }
    /**
     * Spy an instance of an object in the container.
     *
     * @param  string  $abstract
     * @return \Mockery\MockInterface
     */
    protected function spy($abstract, ?Closure $mock = null)
    {
        return $this->instance($abstract, Mockery::spy(...array_filter(func_get_args())));
    }
    /**
     * Instruct the container to forget a previously mocked / spied instance of an object.
     *
     * @param  string  $abstract
     * @return $this
     */
    protected function forget_mock($abstract)
    {
        $this->app->forget_instance($abstract);
        return $this;
    }
    /**
     * Register an empty handler for Vite in the container.
     *
     * @return $this
     */
    protected function without_vite()
    {
        if ($this->original_vite == null) {
            $this->original_vite = app(Vite::class);
        }
        Vite_Facade::clear_resolved_instance();
        $this->swap(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $build_directory = null): \Illuminate\Support\Html_String
            {
                return new Html_String('');
            }
            public function __call($method, $parameters)
            {
                return '';
            }
            public function __toString(): string
            {
                return '';
            }
            public function use_integrity_key($key): self
            {
                return $this;
            }
            public function use_build_directory($path): self
            {
                return $this;
            }
            public function use_hot_file($path): self
            {
                return $this;
            }
            public function with_entry_points($entry_points): self
            {
                return $this;
            }
            public function use_script_tag_attributes($attributes): self
            {
                return $this;
            }
            public function use_style_tag_attributes($attributes): self
            {
                return $this;
            }
            public function use_preload_tag_attributes($attributes): self
            {
                return $this;
            }
            public function preloaded_assets(): array
            {
                return [];
            }
            public function react_refresh(): string
            {
                return '';
            }
            public function content($asset, $build_directory = null): string
            {
                return '';
            }
            public function asset($asset, $build_directory = null): string
            {
                return '';
            }
        });
        return $this;
    }
    /**
     * Restore Vite in the container.
     *
     * @return $this
     */
    protected function with_vite()
    {
        if ($this->original_vite) {
            $this->app->instance(Vite::class, $this->original_vite);
        }
        return $this;
    }
    /**
     * Register an empty handler for Laravel Mix in the container.
     *
     * @return $this
     */
    protected function without_mix()
    {
        if ($this->original_mix == null) {
            $this->original_mix = app(Mix::class);
        }
        $this->swap(Mix::class, fn(): \Illuminate\Support\Html_String => new Html_String(''));
        return $this;
    }
    /**
     * Restore Laravel Mix in the container.
     *
     * @return $this
     */
    protected function with_mix()
    {
        if ($this->original_mix) {
            $this->app->instance(Mix::class, $this->original_mix);
        }
        return $this;
    }
    /**
     * Execute deferred functions immediately.
     *
     * @return $this
     */
    protected function without_defer()
    {
        if ($this->original_deferred_callbacks_collection == null) {
            $this->original_deferred_callbacks_collection = $this->app->make(Deferred_Callback_Collection::class);
        }
        $this->swap(Deferred_Callback_Collection::class, new class extends Deferred_Callback_Collection
        {
            public function offsetSet(mixed $offset, mixed $value): void
            {
                $value();
            }
        });
        return $this;
    }
    /**
     * Restore deferred functions.
     *
     * @return $this
     */
    protected function with_defer()
    {
        if ($this->original_deferred_callbacks_collection) {
            $this->app->instance(Deferred_Callback_Collection::class, $this->original_deferred_callbacks_collection);
        }
        return $this;
    }
}