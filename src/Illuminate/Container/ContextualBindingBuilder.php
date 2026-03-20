<?php

declare (strict_types=1);
namespace Illuminate\Container;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\Contextual_Binding_Builder as ContextualBindingBuilderContract;
class Contextual_Binding_Builder implements Contextual_Binding_Builder_Contract
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
    )
    {
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
        foreach (Util::array_wrap($this->concrete) as $concrete) {
            $this->container->add_contextual_binding($concrete, $this->needs, $implementation);
        }
        return $this;
    }
    /**
     * Define tagged services to be used as the implementation for the contextual binding.
     *
     * @param  string  $tag
     * @return $this
     */
    public function give_tagged($tag): static
    {
        return $this->give(function ($container) use ($tag): array {
            $tagged_services = $container->tagged($tag);
            return is_array($tagged_services) ? $tagged_services : iterator_to_array($tagged_services);
        });
    }
    /**
     * Specify the configuration item to bind as a primitive.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return $this
     */
    public function give_config($key, $default = null): static
    {
        return $this->give(fn($container) => $container->get('config')->get($key, $default));
    }
}