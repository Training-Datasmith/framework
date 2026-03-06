<?php

declare(strict_types=1);

namespace Illuminate\Container;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualBindingBuilder as ContextualBindingBuilderContract;

class ContextualBindingBuilder implements ContextualBindingBuilderContract
{
    /**
     * The abstract target.
     *
     * @var string
     */
    protected $needs;

    /**
     * Create a new contextual binding builder.
     *
     * @param  string|array  $concrete
     */
    public function __construct(
        /**
         * The underlying container instance.
         */
        protected \Illuminate\Contracts\Container\Container $container,
        /**
         * The concrete instance.
         */
        protected $concrete
    ) {
    }

    /**
     * Define the abstract target that depends on the context.
     *
     * @param  string  $abstract
     * @return $this
     */
    public function needs($abstract): static
    {
        $this->needs = $abstract;

        return $this;
    }

    /**
     * Define the implementation for the contextual binding.
     *
     * @param  \Closure|string|array  $implementation
     * @return $this
     */
    public function give($implementation): static
    {
        foreach (Util::arrayWrap($this->concrete) as $concrete) {
            $this->container->addContextualBinding($concrete, $this->needs, $implementation);
        }

        return $this;
    }

    /**
     * Define tagged services to be used as the implementation for the contextual binding.
     *
     * @param  string  $tag
     * @return $this
     */
    public function giveTagged($tag): static
    {
        return $this->give(function ($container) use ($tag): array {
            $taggedServices = $container->tagged($tag);

            return is_array($taggedServices) ? $taggedServices : iterator_to_array($taggedServices);
        });
    }

    /**
     * Specify the configuration item to bind as a primitive.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return $this
     */
    public function giveConfig($key, $default = null): static
    {
        return $this->give(fn ($container) => $container->get('config')->get($key, $default));
    }
}
